<?php

namespace App\Services;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandReviewResponse;
use App\Models\DemandTask;
use App\Models\KnowledgeItem;
use App\Models\TaskTimeEntry;
use App\Models\TeamCapacitySnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AiAgentTools
{
    /** @return list<array<string, mixed>> */
    public function definitions(User $user, ?Demand $demand, string $agent = 'organization_assistant'): array
    {
        $tools = [];
        if ($demand && in_array($agent, ['demand_assistant', 'approval_assistant'], true)) {
            $tools[] = $this->tool('read_demand_context', 'Lê o resumo autorizado desta demanda e as tarefas atribuídas à pessoa que perguntou.', [
                'type' => 'object', 'properties' => new \stdClass, 'required' => [], 'additionalProperties' => false,
            ]);
        }
        if (in_array($agent, ['demand_assistant', 'organization_assistant', 'knowledge_assistant'], true)) {
            $tools[] = $this->tool('search_knowledge', 'Busca referências e instruções internas ativas desta organização por palavras-chave.', [
                'type' => 'object',
                'properties' => ['query' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 180]],
                'required' => ['query'], 'additionalProperties' => false,
            ]);
        }

        $canManage = $demand ? $user->can('manage', $demand) : in_array($user->role, [UserRole::AgencyOwner, UserRole::MarketingManager], true);
        if ($canManage && in_array($agent, ['demand_assistant', 'organization_assistant'], true)) {
            $tools[] = $this->tool('search_organization_demands', 'Busca até cinco demandas da própria organização por parte do título e retorna somente etapa, data de atualização e quantidade de tarefas; não retorna briefing nem dados de clientes.', [
                'type' => 'object',
                'properties' => ['query' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 180]],
                'required' => ['query'], 'additionalProperties' => false,
            ]);
            if ($demand && in_array($agent, ['demand_assistant', 'approval_assistant'], true)) {
                $tools[] = $this->tool('list_client_feedback', 'Lê comentários e decisões do cliente desta demanda, sem revelar token ou arquivo privado.', [
                    'type' => 'object', 'properties' => new \stdClass, 'required' => [], 'additionalProperties' => false,
                ]);
            }
        }

        if ($canManage && $demand && $agent === 'approval_assistant') {
            $tools[] = $this->tool('list_client_feedback', 'Lê comentários e decisões do cliente desta demanda, sem revelar token ou arquivo privado.', [
                'type' => 'object', 'properties' => new \stdClass, 'required' => [], 'additionalProperties' => false,
            ]);
        }

        if (in_array($user->role, [UserRole::AgencyOwner, UserRole::MarketingManager], true) && in_array($agent, ['operations_assistant', 'organization_assistant'], true)) {
            $tools[] = $this->tool('summarize_team_activity', 'Lê contagens factuais da equipe desta organização: tarefas abertas por estado, estimativas, conclusões e tempo registrado nos últimos 30 dias. Não calcula capacidade nem pontua pessoas.', [
                'type' => 'object', 'properties' => new \stdClass, 'required' => [], 'additionalProperties' => false,
            ]);
        }

        if (in_array($user->role, [UserRole::AgencyOwner, UserRole::MarketingManager], true) && $agent === 'operations_assistant') {
            $tools[] = $this->tool('summarize_weekly_capacity', 'Consulta a prévia manual da semana ISO atual: horas registradas, ausências descontadas, estimativas abertas com prazo na semana e itens sem dados. Não revela títulos de tarefas nem sugere redistribuição.', [
                'type' => 'object', 'properties' => new \stdClass, 'required' => [], 'additionalProperties' => false,
            ]);
        }

        return $tools;
    }

    /** @return array{result: array<string, mixed>, receipt: array<string, mixed>} */
    public function execute(string $name, mixed $arguments, User $user, ?Demand $demand): array
    {
        $arguments = is_array($arguments) ? $arguments : [];

        return match ($name) {
            'read_demand_context' => $demand ? $this->readDemandContext($user, $demand) : throw ValidationException::withMessages(['ai' => 'Não há demanda ativa nesta consulta.']),
            'search_knowledge' => $this->searchKnowledge($arguments, $user),
            'list_client_feedback' => $demand ? $this->listClientFeedback($user, $demand) : throw ValidationException::withMessages(['ai' => 'Não há demanda ativa nesta consulta.']),
            'summarize_team_activity' => $this->summarizeTeamActivity($user),
            'summarize_weekly_capacity' => $this->summarizeWeeklyCapacity($user),
            'search_organization_demands' => $this->searchOrganizationDemands($arguments, $user),
            default => throw ValidationException::withMessages(['ai' => 'A ferramenta solicitada não está autorizada.']),
        };
    }

    /** @return array{result: array<string, mixed>, receipt: array<string, mixed>} */
    private function searchOrganizationDemands(array $arguments, User $user): array
    {
        abort_unless(in_array($user->role, [UserRole::AgencyOwner, UserRole::MarketingManager], true), 403);
        $validated = Validator::make($arguments, [
            'query' => ['required', 'string', 'min:2', 'max:180'],
        ])->validate();
        $term = trim($validated['query']);
        $demands = Demand::query()
            ->where('organization_id', $user->organization_id)
            ->where('title', 'like', '%'.$term.'%')
            ->withCount('tasks')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'title', 'status', 'updated_at']);
        $results = $demands->map(fn (Demand $demand) => [
            'title' => $demand->title,
            'stage' => $demand->status->label(),
            'tasks' => (int) $demand->tasks_count,
            'updated_at' => $demand->updated_at?->toIso8601String(),
        ])->all();

        return [
            'result' => ['query' => $term, 'demands' => $results],
            'receipt' => ['tool' => 'search_organization_demands', 'source' => 'Demandas da organização', 'items' => count($results)],
        ];
    }

    /** @return array{result: array<string, mixed>, receipt: array<string, mixed>} */
    private function summarizeTeamActivity(User $user): array
    {
        abort_unless(in_array($user->role, [UserRole::AgencyOwner, UserRole::MarketingManager], true), 403);
        $since = CarbonImmutable::now()->subDays(30);
        $now = CarbonImmutable::now();

        $professionals = User::query()
            ->where('organization_id', $user->organization_id)
            ->where('role', UserRole::Professional->value)
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
        $professionalIds = $professionals->modelKeys();

        $taskGroups = DemandTask::query()
            ->selectRaw('assigned_to, status, COUNT(*) as task_count, SUM(CASE WHEN status != ? THEN COALESCE(estimate_minutes, 0) ELSE 0 END) as open_estimate_minutes', [TaskStatus::Completed->value])
            ->where('organization_id', $user->organization_id)
            ->whereIn('assigned_to', $professionalIds)
            ->groupBy('assigned_to', 'status')
            ->get()
            ->groupBy('assigned_to');
        $completedCounts = DemandTask::query()
            ->selectRaw('assigned_to, COUNT(*) as task_count')
            ->where('organization_id', $user->organization_id)
            ->whereIn('assigned_to', $professionalIds)
            ->where('status', TaskStatus::Completed->value)
            ->where('completed_at', '>=', $since)
            ->groupBy('assigned_to')
            ->pluck('task_count', 'assigned_to');
        $recordedSeconds = array_fill_keys($professionalIds, 0);
        TaskTimeEntry::query()
            ->where('organization_id', $user->organization_id)
            ->whereIn('user_id', $professionalIds)
            ->where('started_at', '>=', $since)
            ->orderBy('id')
            ->cursor()
            ->each(function (TaskTimeEntry $entry) use (&$recordedSeconds, $now): void {
                $recordedSeconds[$entry->user_id] += (int) $entry->started_at->diffInSeconds($entry->ended_at ?? $now);
            });

        $rows = $professionals->map(function (User $professional) use ($taskGroups, $completedCounts, $recordedSeconds): array {
            $byStatus = $taskGroups->get($professional->id, collect())->keyBy('status');
            $counts = [];
            foreach ([TaskStatus::Todo, TaskStatus::InProgress, TaskStatus::Paused, TaskStatus::Blocked] as $status) {
                $counts[$status->value] = (int) ($byStatus->get($status->value)->task_count ?? 0);
            }

            return [
                'professional' => $professional->name,
                'active' => $professional->is_active,
                'open_tasks_by_status' => $counts,
                'open_estimate_minutes' => (int) $byStatus->sum('open_estimate_minutes'),
                'completed_tasks_last_30_days' => (int) ($completedCounts[$professional->id] ?? 0),
                'recorded_seconds_last_30_days' => $recordedSeconds[$professional->id] ?? 0,
            ];
        })->all();

        return [
            'result' => ['period_days' => 30, 'professionals' => $rows, 'interpretation' => 'São registros operacionais, não avaliação, ranking ou cálculo de disponibilidade/capacidade.'],
            'receipt' => ['tool' => 'summarize_team_activity', 'source' => 'Atividade da equipe da organização', 'items' => count($rows)],
        ];
    }

    /** @return array{result: array<string, mixed>, receipt: array<string, mixed>} */
    private function summarizeWeeklyCapacity(User $user): array
    {
        abort_unless($user->is_active && $user->organization_id !== null
            && in_array($user->role, [UserRole::AgencyOwner, UserRole::MarketingManager], true), 403);

        $now = CarbonImmutable::now();
        $weekStart = $now->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
        $weekEnd = $weekStart->addDays(6)->endOfDay();
        $professionals = User::query()
            ->where('organization_id', $user->organization_id)
            ->where('role', UserRole::Professional->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $professionalIds = $professionals->modelKeys();
        $snapshots = TeamCapacitySnapshot::query()
            ->where('organization_id', $user->organization_id)
            ->whereIn('professional_id', $professionalIds)
            ->whereDate('week_start', $weekStart->toDateString())
            ->orderByDesc('id')
            ->get(['professional_id', 'scheduled_minutes', 'absences'])
            ->unique('professional_id')
            ->keyBy('professional_id');
        $tasksByProfessional = DemandTask::query()
            ->where('organization_id', $user->organization_id)
            ->whereIn('assigned_to', $professionalIds)
            ->where('status', '!=', TaskStatus::Completed->value)
            ->whereHas('demand', fn ($query) => $query->where('status', '!=', DemandStatus::Completed->value))
            ->get(['id', 'assigned_to', 'estimate_minutes', 'planned_due_on'])
            ->groupBy('assigned_to');

        $rows = $professionals->map(function (User $professional) use ($snapshots, $tasksByProfessional, $weekStart, $weekEnd): array {
            $snapshot = $snapshots->get($professional->id);
            $tasks = $tasksByProfessional->get($professional->id, collect());
            $datedTasks = $tasks->filter(fn (DemandTask $task) => $task->planned_due_on
                && $task->planned_due_on->betweenIncluded($weekStart, $weekEnd));
            $absenceMinutes = (int) collect($snapshot?->absences ?? [])->sum('minutes');
            $availableMinutes = $snapshot?->scheduled_minutes === null
                ? null
                : max(0, (int) $snapshot->scheduled_minutes - $absenceMinutes);
            $estimatedMinutes = (int) $datedTasks->sum(fn (DemandTask $task) => max(0, (int) $task->estimate_minutes));

            return [
                'professional' => $professional->name,
                'week' => $weekStart->format('o-\\WW'),
                'scheduled_minutes_recorded' => $snapshot?->scheduled_minutes,
                'absence_minutes_recorded' => $absenceMinutes,
                'available_minutes_after_absences' => $availableMinutes,
                'open_estimate_minutes_due_this_week' => $estimatedMinutes,
                'dated_open_tasks_due_this_week' => $datedTasks->count(),
                'dated_tasks_missing_estimate' => $datedTasks->filter(fn (DemandTask $task) => ! $task->estimate_minutes || $task->estimate_minutes < 1)->count(),
                'open_tasks_without_due_date' => $tasks->whereNull('planned_due_on')->count(),
                'capacity_recorded' => $snapshot !== null && $snapshot->scheduled_minutes !== null,
            ];
        })->all();

        return [
            'result' => [
                'week' => $weekStart->format('o-\\WW'),
                'professionals' => $rows,
                'interpretation' => 'Dados da prévia manual atual. Horas e ausências foram registradas pela gestão; estimativas são integrais por prazo, não distribuição diária. Não representam regra oficial de jornada, avaliação ou recomendação de alocação.',
            ],
            'receipt' => ['tool' => 'summarize_weekly_capacity', 'source' => 'Prévia semanal manual da equipe', 'items' => count($rows)],
        ];
    }

    /** @return array{result: array<string, mixed>, receipt: array<string, mixed>} */
    private function readDemandContext(User $user, Demand $demand): array
    {
        abort_unless($user->can('view', $demand), 403);

        $tasks = $demand->tasks()
            ->when($user->role === UserRole::Professional, fn ($query) => $query->where('assigned_to', $user->id))
            ->orderBy('id')
            ->get(['id', 'title', 'description', 'status', 'estimate_minutes'])
            ->map(fn ($task) => [
                'title' => $task->title,
                'description' => mb_substr((string) $task->description, 0, 400),
                'status' => $task->status->label(),
                'estimate_minutes' => $task->estimate_minutes,
            ])->all();

        return [
            'result' => [
                'title' => $demand->title,
                'brief' => mb_substr($demand->brief, 0, 6000),
                'responsible' => $demand->responsible()->value('name'),
                'materials_location' => $demand->materials_location,
                'access_instructions' => $demand->access_instructions,
                'stage' => $demand->status->label(),
                'tasks' => $tasks,
            ],
            'receipt' => ['tool' => 'read_demand_context', 'source' => $demand->title, 'items' => count($tasks)],
        ];
    }

    /** @return array{result: array<string, mixed>, receipt: array<string, mixed>} */
    private function searchKnowledge(array $arguments, User $user): array
    {
        abort_unless($user->can('viewAny', KnowledgeItem::class), 403);
        $validated = Validator::make($arguments, [
            'query' => ['required', 'string', 'min:2', 'max:180'],
        ])->validate();

        $term = trim($validated['query']);
        $words = collect(preg_split('/\s+/u', $term) ?: [])->filter()->take(6)->values();
        if ($words->isEmpty()) {
            return ['result' => ['items' => []], 'receipt' => ['tool' => 'search_knowledge', 'source' => '', 'items' => 0]];
        }

        $items = KnowledgeItem::query()
            ->where('organization_id', $user->organization_id)
            ->whereNull('archived_at')
            ->where(function ($query) use ($words): void {
                $query->where(function ($title) use ($words): void {
                    foreach ($words as $word) {
                        $title->where('title', 'like', '%'.$word.'%');
                    }
                })->orWhere(function ($content) use ($words): void {
                    foreach ($words as $word) {
                        $content->where('content', 'like', '%'.$word.'%');
                    }
                });
            })
            ->orderByDesc('updated_at')
            ->limit(4)
            ->get(['id', 'type', 'title', 'content', 'url', 'steps']);

        $results = $items->map(fn (KnowledgeItem $item) => [
            'title' => $item->title,
            'type' => $item->type,
            'content' => mb_substr($item->content, 0, 1200),
            'url' => $item->url,
            'steps' => array_slice($item->steps ?? [], 0, 12),
        ])->all();

        return [
            'result' => ['items' => $results],
            'receipt' => ['tool' => 'search_knowledge', 'source' => $term, 'items' => count($results)],
        ];
    }

    /** @return array{result: array<string, mixed>, receipt: array<string, mixed>} */
    private function listClientFeedback(User $user, Demand $demand): array
    {
        abort_unless($user->can('manage', $demand), 403);
        $feedback = DemandReviewResponse::query()
            ->whereHas('reviewLink', fn ($query) => $query->where('demand_id', $demand->id))
            ->with('reviewLink:id,version')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->reverse()
            ->map(fn (DemandReviewResponse $response) => [
                'response_id' => $response->id,
                'version' => $response->reviewLink->version,
                'type' => $response->type,
                'comment' => $response->comment,
                'anchor_type' => $response->anchor_type,
                'anchor' => $response->anchor_data,
                'created_at' => $response->created_at?->toIso8601String(),
            ])->values()->all();

        return [
            'result' => ['feedback' => $feedback],
            'receipt' => ['tool' => 'list_client_feedback', 'source' => 'Feedback de clientes', 'items' => count($feedback)],
        ];
    }

    /** @param array<string, mixed> $parameters */
    private function tool(string $name, string $description, array $parameters): array
    {
        return ['type' => 'function', 'function' => [
            'name' => $name, 'description' => $description, 'parameters' => $parameters,
        ]];
    }
}
