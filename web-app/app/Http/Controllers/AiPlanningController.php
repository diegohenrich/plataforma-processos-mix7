<?php

namespace App\Http\Controllers;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\AiPlanningRun;
use App\Models\Demand;
use App\Models\DemandEvent;
use App\Services\PlanningAgent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

        try {
            $result = $agent->propose($demand);
        } catch (RuntimeException|JsonException $exception) {
            return back()->withErrors(['ai' => $exception->getMessage()]);
        }

        $run = DB::transaction(function () use ($demand, $request, $result): AiPlanningRun {
            $run = AiPlanningRun::create([
                'organization_id' => $demand->organization_id,
                'demand_id' => $demand->id,
                'requested_by' => $request->user()->id,
                'provider' => (string) config('services.ai_gateway.provider'),
                'model' => (string) config('services.ai_gateway.model'),
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

    public function approve(Request $request, Demand $demand, AiPlanningRun $run): RedirectResponse
    {
        $this->authorize('manage', $demand);
        abort_unless($run->demand_id === $demand->id && $run->organization_id === $request->user()->organization_id, 404);
        if ($run->status !== 'pending') {
            return back()->withErrors(['ai' => 'Esta proposta já foi revisada e não pode ser aplicada novamente.']);
        }

        $organizationId = $request->user()->organization_id;
        $data = Validator::make($request->all(), [
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

        $reviewed = DB::transaction(function () use ($data, $selectedTasks, $demand, $request, $run): bool {
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
                $task = $demand->tasks()->create([
                    'organization_id' => $demand->organization_id,
                    'created_by' => $request->user()->id,
                    'assigned_to' => $taskData['assignee_id'],
                    'title' => trim($taskData['title']),
                    'description' => 'Perfil: '.trim($taskData['responsibility_profile'])."\nMotivo: ".($original[$index]['rationale'] ?? 'Definido durante a revisão da proposta.'),
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
            }

            $locked->update([
                'reviewed_tasks' => [
                    'summary' => $locked->proposal['summary'] ?? '',
                    'questions' => $data['questions'] ?? [],
                    'tasks' => array_values(array_map(function (int $index, array $suggested) use ($selectedTasks): array {
                        if (! isset($selectedTasks[$index])) {
                            return $suggested + ['include' => false];
                        }

                        return $selectedTasks[$index] + [
                            'include' => true,
                            'rationale' => $suggested['rationale'] ?? '',
                            'depends_on' => $selectedTasks[$index]['depends_on'],
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
