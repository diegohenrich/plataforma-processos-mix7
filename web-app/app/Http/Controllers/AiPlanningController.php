<?php

namespace App\Http\Controllers;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\AiPlanningRun;
use App\Models\Demand;
use App\Models\DemandEvent;
use App\Models\DemandTask;
use App\Models\TeamCapacitySnapshot;
use App\Models\User;
use App\Services\AiProviderSettings;
use App\Services\PlanningAgent;
use App\Services\TaskAssignmentNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;

class AiPlanningController extends Controller
{
    public function propose(Request $request, Demand $demand, PlanningAgent $agent): RedirectResponse
    {
        $this->authorize('manage', $demand);
        if ($demand->status !== DemandStatus::Planning) {
            return back()->withErrors(['ai' => 'A proposta de IA só pode ser gerada durante o planejamento.']);
        }

        $data = $request->validate([
            'include_client_feedback' => ['sometimes', 'boolean'],
            'include_team_capacity' => ['sometimes', 'boolean'],
            'include_assignment_candidates' => ['sometimes', 'boolean'],
            'capacity_week' => ['required_if:include_team_capacity,1', 'nullable', 'regex:/^\d{4}-W\d{2}$/', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! preg_match('/^\d{4}-W\d{2}$/', $value)) {
                    return;
                }
                [$year, $weekNumber] = array_map('intval', explode('-W', $value));
                if ($weekNumber < 1 || $weekNumber > 53 || CarbonImmutable::now()->setISODate($year, $weekNumber)->format('o-\\WW') !== $value) {
                    $fail('Escolha uma semana válida.');
                }
            }],
        ]);
        $feedback = ($data['include_client_feedback'] ?? false) ? $this->clientFeedback($demand) : [];
        $teamCapacity = ($data['include_team_capacity'] ?? false)
            ? $this->teamCapacity($demand, $data['capacity_week'])
            : [];
        [$assignmentCandidates, $assignmentCandidateMap] = ($data['include_assignment_candidates'] ?? false)
            ? $this->assignmentCandidates($demand)
            : [[], []];

        try {
            $result = $agent->propose($demand, $feedback, $teamCapacity, $assignmentCandidates);
        } catch (RuntimeException|JsonException $exception) {
            return back()->withErrors(['ai' => $exception->getMessage()]);
        }

        $result['proposal']['_source'] = [
            'client_feedback_included' => $feedback !== [],
            'feedback_response_ids' => array_column($feedback, 'response_id'),
            'feedback_versions' => array_column($feedback, 'version', 'response_id'),
            'team_capacity_included' => $teamCapacity !== [],
            'team_capacity' => $teamCapacity,
            'assignment_suggestions_requested' => (bool) ($data['include_assignment_candidates'] ?? false),
            'assignment_candidates_included' => $assignmentCandidates !== [],
            'assignment_candidates' => $assignmentCandidateMap,
        ];

        $providerSettings = app(AiProviderSettings::class)->forOrganization((int) $demand->organization_id);
        $run = DB::transaction(function () use ($demand, $request, $result, $providerSettings): AiPlanningRun {
            $run = AiPlanningRun::create([
                'organization_id' => $demand->organization_id,
                'demand_id' => $demand->id,
                'requested_by' => $request->user()->id,
                'provider' => $providerSettings['provider'],
                'model' => $providerSettings['model'] ?: $providerSettings['provider'],
                'input_hash' => $result['input_hash'],
                'input_characters' => $result['input_characters'],
                'input_tokens' => $result['usage']['input_tokens'],
                'output_tokens' => $result['usage']['output_tokens'],
                'proposal' => $result['proposal'],
                'status' => 'pending',
            ]);

            DemandEvent::create([
                'organization_id' => $demand->organization_id,
                'demand_id' => $demand->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'ai_planning_proposed',
                'summary' => $request->user()->name.' gerou uma proposta de planejamento com IA. Nenhuma tarefa foi criada.',
            ]);

            return $run;
        });

        return back()->with('success', 'A proposta foi gerada. Revise e escolha os responsáveis antes de aplicar.')
            ->with('ai_planning_run_id', $run->id);
    }

    /** @return array<string, int|string> */
    private function teamCapacity(Demand $demand, string $week): array
    {
        [$year, $weekNumber] = array_map('intval', explode('-W', $week));
        $weekStart = CarbonImmutable::now()->setISODate($year, $weekNumber)->startOfWeek()->startOfDay();
        if ($weekStart->format('o-\\WW') !== $week) {
            throw ValidationException::withMessages(['capacity_week' => 'Escolha uma semana válida.']);
        }
        $weekEnd = $weekStart->addDays(6)->endOfDay();
        $professionalIds = User::query()
            ->where('organization_id', $demand->organization_id)
            ->where('role', UserRole::Professional->value)
            ->where('is_active', true)
            ->pluck('id');
        $snapshots = TeamCapacitySnapshot::query()
            ->where('organization_id', $demand->organization_id)
            ->whereIn('professional_id', $professionalIds)
            ->whereDate('week_start', $weekStart->toDateString())
            ->orderByDesc('id')
            ->get(['professional_id', 'scheduled_minutes', 'absences'])
            ->unique('professional_id')
            ->keyBy('professional_id');
        $scheduledSnapshots = $snapshots->filter(fn (TeamCapacitySnapshot $snapshot) => $snapshot->scheduled_minutes !== null);
        $absenceMinutes = (int) $scheduledSnapshots->sum(fn (TeamCapacitySnapshot $snapshot) => collect($snapshot->absences ?? [])->sum('minutes'));
        $availableMinutes = (int) $scheduledSnapshots->sum(fn (TeamCapacitySnapshot $snapshot) => max(0, (int) $snapshot->scheduled_minutes - (int) collect($snapshot->absences ?? [])->sum('minutes')));
        $openTasks = DemandTask::query()
            ->where('organization_id', $demand->organization_id)
            ->whereIn('assigned_to', $professionalIds)
            ->where('status', '!=', TaskStatus::Completed->value)
            ->whereHas('demand', fn ($query) => $query->where('status', '!=', DemandStatus::Completed->value))
            ->get(['estimate_minutes', 'planned_due_on']);
        $dueTasks = $openTasks->filter(fn (DemandTask $task) => $task->planned_due_on
            && $task->planned_due_on->betweenIncluded($weekStart, $weekEnd));

        return [
            'week' => $weekStart->format('o-\\WW'),
            'active_professionals' => $professionalIds->count(),
            'professionals_with_recorded_capacity' => $scheduledSnapshots->count(),
            'professionals_without_recorded_capacity' => max(0, $professionalIds->count() - $scheduledSnapshots->count()),
            'recorded_available_minutes' => $availableMinutes,
            'recorded_absence_minutes' => $absenceMinutes,
            'open_estimate_minutes_due_this_week' => (int) $dueTasks->sum(fn (DemandTask $task) => max(0, (int) $task->estimate_minutes)),
            'dated_open_tasks_due_this_week' => $dueTasks->count(),
            'dated_tasks_missing_estimate' => $dueTasks->filter(fn (DemandTask $task) => ! $task->estimate_minutes || $task->estimate_minutes < 1)->count(),
            'open_tasks_without_due_date' => $openTasks->whereNull('planned_due_on')->count(),
        ];
    }

    /** @return array{list<array{candidate_ref: string, specialties: list<string>}>, array<string, array{user_id: int, name: string, specialties: list<string>}>} */
    private function assignmentCandidates(Demand $demand): array
    {
        $candidates = [];
        $localMap = [];
        $professionals = User::query()
            ->where('organization_id', $demand->organization_id)
            ->where('role', UserRole::Professional->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'specialties']);

        foreach ($professionals as $professional) {
            $specialties = collect($professional->specialties ?? [])
                ->filter(fn (mixed $specialty): bool => is_string($specialty) && trim($specialty) !== '')
                ->map(fn (string $specialty): string => trim($specialty))
                ->unique(fn (string $specialty): string => mb_strtolower($specialty))
                ->take(12)
                ->values()
                ->all();
            if ($specialties === []) {
                continue;
            }

            $reference = Str::random(16);
            $candidates[] = ['candidate_ref' => $reference, 'specialties' => $specialties];
            $localMap[$reference] = [
                'user_id' => $professional->id,
                'name' => $professional->name,
                'specialties' => $specialties,
            ];
        }

        return [$candidates, $localMap];
    }

    public function approve(Request $request, Demand $demand, AiPlanningRun $run, TaskAssignmentNotifier $taskAssignmentNotifier): RedirectResponse
    {
        $this->authorize('manage', $demand);
        abort_unless($run->demand_id === $demand->id && $run->organization_id === $request->user()->organization_id, 404);
        if ($run->status !== 'pending') {
            return back()->withErrors(['ai' => 'Esta proposta já foi revisada e não pode ser aplicada novamente.']);
        }

        $organizationId = $request->user()->organization_id;
        $data = Validator::make($request->all(), [
            'summary' => ['nullable', 'string', 'max:280'],
            'tasks' => ['required', 'array', 'max:20'],
            'tasks.*.include' => ['required', 'boolean'],
            'questions' => ['nullable', 'array', 'max:8'],
            'questions.*' => ['required', 'string', 'max:500'],
        ])->validate();

        if (count($data['tasks']) !== count($run->proposal['tasks'] ?? [])) {
            return back()->withErrors(['ai' => 'A proposta mudou. Atualize a página antes de aplicar as tarefas.']);
        }

        $selectedTasks = [];
        $submittedTasks = $request->input('tasks', []);
        foreach ($submittedTasks as $index => $taskData) {
            if (! filter_var($taskData['include'], FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $taskIndex = (int) $index;
            $validated = Validator::make(['tasks' => [$taskIndex => $taskData]], [
                "tasks.$taskIndex.title" => ['required', 'string', 'max:180'],
                "tasks.$taskIndex.responsibility_profile" => ['required', 'string', 'max:120'],
                "tasks.$taskIndex.estimate_minutes" => ['required', 'integer', 'min:1', 'max:100000'],
                "tasks.$taskIndex.assignee_id" => [
                    'required', 'integer',
                    Rule::exists('users', 'id')->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->where('role', UserRole::Professional->value)
                        ->where('is_active', true)),
                ],
            ])->validate();
            $selectedTasks[$taskIndex] = $validated['tasks'][$taskIndex];
        }

        if ($selectedTasks === []) {
            return back()->withErrors(['ai' => 'Mantenha ao menos uma tarefa selecionada para aplicar a proposta.']);
        }

        $titles = array_map(fn (array $task): string => mb_strtolower(trim($task['title'])), $selectedTasks);
        if (count(array_unique($titles)) !== count($titles)) {
            return back()->withErrors(['ai' => 'Cada tarefa aplicada precisa ter um título diferente.']);
        }

        if ($demand->status !== DemandStatus::Planning) {
            return back()->withErrors(['ai' => 'A etapa mudou. Volte ao planejamento para aplicar esta proposta.']);
        }

        $reviewed = DB::transaction(function () use ($data, $selectedTasks, $demand, $request, $run, $taskAssignmentNotifier): bool {
            $locked = AiPlanningRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                return false;
            }

            $original = $locked->proposal['tasks'] ?? [];
            foreach ($selectedTasks as $index => &$taskData) {
                $taskData['depends_on'] = $this->expandDependencies($original, $index, array_keys($selectedTasks));
            }
            unset($taskData);

            $createdTasks = [];
            foreach ($selectedTasks as $index => $taskData) {
                $feedbackRefs = array_values(array_unique(array_map('intval', $original[$index]['feedback_refs'] ?? [])));
                $feedbackVersions = $run->proposal['_source']['feedback_versions'] ?? [];
                $feedbackSource = $feedbackRefs === [] ? '' : "\nFeedback do cliente: ".implode(', ', array_map(
                    fn (int $id): string => 'versão '.($feedbackVersions[$id] ?? $feedbackVersions[(string) $id] ?? '?').', resposta #'.$id,
                    $feedbackRefs,
                ));
                $suggestedAssignment = $original[$index]['suggested_assignee_ref'] ?? null;
                $assignmentRationale = trim((string) ($original[$index]['assignment_rationale'] ?? ''));
                $candidateMap = $run->proposal['_source']['assignment_candidates'] ?? [];
                $suggestedPerson = $suggestedAssignment ? ($candidateMap[$suggestedAssignment] ?? null) : null;
                $assignmentNote = $suggestedPerson
                    ? "\nSugestão da IA: {$suggestedPerson['name']}. Responsável confirmado pela gestão: ".User::query()->whereKey($taskData['assignee_id'])->value('name').'. Motivo sugerido: '.($assignmentRationale ?: 'especialidades declaradas correspondentes.')
                    : '';
                $task = $demand->tasks()->create([
                    'organization_id' => $demand->organization_id,
                    'created_by' => $request->user()->id,
                    'assigned_to' => $taskData['assignee_id'],
                    'title' => trim($taskData['title']),
                    'description' => 'Perfil: '.trim($taskData['responsibility_profile'])."\nMotivo: ".($original[$index]['rationale'] ?? 'Definido durante a revisão da proposta.').$feedbackSource.$assignmentNote,
                    'status' => TaskStatus::Todo,
                    'estimate_minutes' => $taskData['estimate_minutes'],
                ]);
                $createdTasks[$index] = $task;
                if ($taskData['depends_on'] !== []) {
                    $task->dependencies()->sync(array_map(fn (int $dependency) => $createdTasks[$dependency]->id, $taskData['depends_on']));
                }

                DemandEvent::create([
                    'organization_id' => $demand->organization_id,
                    'demand_id' => $demand->id,
                    'task_id' => $task->id,
                    'actor_id' => $request->user()->id,
                    'event_type' => 'task_assigned',
                    'summary' => $request->user()->name.' aprovou a proposta de IA e atribuiu "'.$task->title.'" a '.$task->assignee()->value('name').'.',
                ]);
                $taskAssignmentNotifier->notify($task, $request->user());
            }

            $reviewedSummary = trim((string) ($data['summary'] ?? ''));
            $demand->update(['ai_summary' => $reviewedSummary !== '' ? $reviewedSummary : null]);
            $locked->update([
                'reviewed_tasks' => [
                    'summary' => $reviewedSummary,
                    'questions' => $data['questions'] ?? [],
                    'tasks' => array_values(array_map(function (int $index, array $suggested) use ($selectedTasks): array {
                        if (! isset($selectedTasks[$index])) {
                            return $suggested + ['include' => false];
                        }

                        return $selectedTasks[$index] + [
                            'include' => true,
                            'rationale' => $suggested['rationale'] ?? '',
                            'depends_on' => $selectedTasks[$index]['depends_on'],
                            'feedback_refs' => $suggested['feedback_refs'] ?? [],
                            'suggested_assignee_ref' => $suggested['suggested_assignee_ref'] ?? null,
                            'assignment_rationale' => $suggested['assignment_rationale'] ?? '',
                        ];
                    }, array_keys($original), $original)),
                ],
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            DemandEvent::create([
                'organization_id' => $demand->organization_id,
                'demand_id' => $demand->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'ai_planning_approved',
                'summary' => $request->user()->name.' revisou e aplicou '.count($selectedTasks).' tarefa(s) da proposta de IA.',
            ]);

            return true;
        });

        return $reviewed
            ? back()->with('success', 'As tarefas selecionadas foram criadas e registradas no histórico.')
            : back()->withErrors(['ai' => 'Esta proposta já foi aplicada em outra solicitação.']);
    }

    /** @param array<int, array{depends_on?: array<int>}> $proposal @param array<int> $selected @return array<int> */
    private function expandDependencies(array $proposal, int $taskIndex, array $selected): array
    {
        $selectedLookup = array_fill_keys($selected, true);
        $resolved = [];
        $visit = function (int $dependency) use (&$visit, &$resolved, $selectedLookup, $proposal, $taskIndex): void {
            if ($dependency < 0 || $dependency >= $taskIndex || isset($resolved[$dependency])) {
                return;
            }

            if (isset($selectedLookup[$dependency])) {
                $resolved[$dependency] = true;

                return;
            }

            foreach ($proposal[$dependency]['depends_on'] ?? [] as $ancestor) {
                if (is_int($ancestor)) {
                    $visit($ancestor);
                }
            }
        };

        foreach ($proposal[$taskIndex]['depends_on'] ?? [] as $dependency) {
            if (is_int($dependency)) {
                $visit($dependency);
            }
        }

        return array_map('intval', array_keys($resolved));
    }

    /** @return list<array{response_id: int, version: int, type: string, comment: string, anchor_type: ?string, anchor: array<string, int|float|string>, created_at: string}> */
    private function clientFeedback(Demand $demand): array
    {
        return $demand->reviewLinks()
            ->with(['responses' => fn ($query) => $query->orderBy('created_at')->orderBy('id')])
            ->orderBy('version')
            ->get(['id', 'version'])
            ->flatMap(fn ($link) => $link->responses->map(fn ($response) => [
                'response_id' => $response->id,
                'version' => $link->version,
                'type' => $response->type,
                'comment' => mb_substr((string) $response->comment, 0, 2000),
                'anchor_type' => $response->anchor_type,
                'anchor' => collect($response->anchor_data ?? [])
                    ->only(['text', 'time', 'page', 'x', 'y', 'width', 'height', 'path'])
                    ->all(),
                'created_at' => $response->created_at->toISOString(),
            ]))
            ->sortBy(fn (array $item) => [$item['created_at'], $item['response_id']])
            ->take(20)
            ->values()
            ->all();
    }

    public function discard(Request $request, Demand $demand, AiPlanningRun $run): RedirectResponse
    {
        $this->authorize('manage', $demand);
        abort_unless($run->demand_id === $demand->id && $run->organization_id === $request->user()->organization_id, 404);

        if ($run->status !== 'pending') {
            return back()->withErrors(['ai' => 'Esta proposta já foi revisada.']);
        }

        DB::transaction(function () use ($demand, $request, $run): void {
            $run->update(['status' => 'discarded', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            DemandEvent::create([
                'organization_id' => $demand->organization_id,
                'demand_id' => $demand->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'ai_planning_discarded',
                'summary' => $request->user()->name.' descartou uma proposta de planejamento da IA.',
            ]);
        });

        return back()->with('success', 'A proposta foi descartada. Nenhuma tarefa foi criada.');
    }
}
