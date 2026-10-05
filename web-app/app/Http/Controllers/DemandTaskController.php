<?php

namespace App\Http\Controllers;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandEvent;
use App\Models\DemandTask;
use App\Models\DemandTaskAssignment;
use App\Models\TaskTimeEntry;
use App\Models\User;
use App\Services\TaskAssignmentNotifier;
use App\Services\TaskTimerHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DemandTaskController extends Controller
{
    public function board(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::AgencyOwner, UserRole::MarketingManager, UserRole::Professional], true), 403);

        $validated = $request->validate(['q' => ['sometimes', 'nullable', 'string', 'max:120']]);
        $search = trim((string) ($validated['q'] ?? ''));

        $perColumn = 30;
        $boardColumns = collect();
        $boardCounts = collect();
        $boardPages = collect();

        foreach (TaskStatus::cases() as $status) {
            $scope = DemandTask::query()
                ->where('organization_id', $user->organization_id)
                ->where('status', $status->value)
                ->when($user->role === UserRole::Professional, fn ($query) => $query->where('assigned_to', $user->id))
                ->when($search !== '', function ($query) use ($search): void {
                    $term = '%'.$search.'%';
                    $query->where(function ($matches) use ($term): void {
                        $matches->where('title', 'like', $term)
                            ->orWhereHas('demand', fn ($demand) => $demand->where('title', 'like', $term))
                            ->orWhereHas('assignee', fn ($assignee) => $assignee->where('name', 'like', $term));
                    });
                });
            $total = (clone $scope)->count();
            $lastPage = max(1, (int) ceil($total / $perColumn));
            $pageKey = 'page_'.$status->value;
            $page = min(max(1, $request->integer($pageKey, 1)), $lastPage);

            $boardColumns->put($status->value, $scope
                ->with(['demand:id,title,status', 'assignee:id,name,is_active', 'dependencies:id,title,status', 'currentAssignment:demand_task_assignments.id,demand_task_assignments.demand_task_id,demand_task_assignments.assigned_at,demand_task_assignments.accepted_at'])
                ->latest()
                ->orderByDesc('id')
                ->offset(($page - 1) * $perColumn)
                ->limit($perColumn)
                ->get());
            $boardCounts->put($status->value, $total);
            $boardPages->put($status->value, [
                'current' => $page,
                'last' => $lastPage,
                'from' => $total === 0 ? 0 : (($page - 1) * $perColumn) + 1,
                'to' => min($page * $perColumn, $total),
                'next_url' => $page < $lastPage ? $request->fullUrlWithQuery([$pageKey => $page + 1]) : null,
                'previous_url' => $page > 1 ? $request->fullUrlWithQuery([$pageKey => $page - 1]) : null,
            ]);
        }

        return view('demands.tasks-board', [
            'boardColumns' => $boardColumns,
            'boardCounts' => $boardCounts,
            'boardPages' => $boardPages,
            'currentUser' => $user,
            'activeTimerTaskId' => $user->role === UserRole::Professional
                ? TaskTimeEntry::query()->where('user_id', $user->id)->whereNull('ended_at')->value('task_id')
                : null,
            'search' => $search,
        ]);
    }

    public function startTimer(Request $request, DemandTask $task): RedirectResponse|JsonResponse
    {
        $this->authorize('trackTime', $task);
        $user = $request->user();

        $result = DB::transaction(function () use ($task, $user): ?string {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $active = TaskTimeEntry::query()->where('user_id', $user->id)->whereNull('ended_at')->first();
            if ($active && $active->task_id !== $task->id) {
                return 'Pause sua tarefa atual antes de iniciar outra.';
            }

            if ($active) {
                return null;
            }

            if ($task->status === TaskStatus::Completed) {
                return 'Uma tarefa concluída não pode iniciar o cronômetro.';
            }

            if ($task->dependencies()->where('status', '!=', TaskStatus::Completed->value)->exists()) {
                return 'Conclua as tarefas anteriores antes de iniciar esta tarefa.';
            }

            $now = CarbonImmutable::now();
            DemandTaskAssignment::query()
                ->where('demand_task_id', $task->id)
                ->where('professional_id', $user->id)
                ->whereNull('released_at')
                ->whereNull('accepted_at')
                ->update(['accepted_at' => $now]);
            TaskTimeEntry::create([
                'organization_id' => $task->organization_id,
                'task_id' => $task->id,
                'user_id' => $user->id,
                'started_at' => $now,
                'last_heartbeat_at' => $now,
            ]);

            if ($task->status !== TaskStatus::InProgress) {
                $from = $task->status;
                $task->update(['status' => TaskStatus::InProgress]);
                DemandEvent::create([
                    'organization_id' => $task->organization_id,
                    'demand_id' => $task->demand_id,
                    'task_id' => $task->id,
                    'actor_id' => $user->id,
                    'event_type' => 'task_status_changed',
                    'summary' => $user->name.' iniciou "'.$task->title.'"',
                    'from_status' => $from->value,
                    'to_status' => TaskStatus::InProgress->value,
                ]);
            }

            DemandEvent::create([
                'organization_id' => $task->organization_id,
                'demand_id' => $task->demand_id,
                'task_id' => $task->id,
                'actor_id' => $user->id,
                'event_type' => 'timer_started',
                'summary' => $user->name.' iniciou o cronômetro de "'.$task->title.'"',
            ]);

            return null;
        });

        if ($result) {
            return $this->actionFailure($request, 'timer', $result, 409);
        }

        $entry = TaskTimeEntry::query()
            ->where('user_id', $user->id)
            ->where('task_id', $task->id)
            ->whereNull('ended_at')
            ->first();

        return $this->actionSuccess($request, $task->fresh(), 'Cronômetro iniciado. O tempo será salvo nesta tarefa.', $entry);
    }

    public function pauseTimer(Request $request, DemandTask $task): RedirectResponse|JsonResponse
    {
        $this->authorize('trackTime', $task);
        $user = $request->user();

        $result = DB::transaction(function () use ($task, $user): ?string {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $entry = TaskTimeEntry::query()
                ->where('user_id', $user->id)
                ->where('task_id', $task->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if (! $entry) {
                return 'Não há cronômetro ativo nesta tarefa.';
            }

            $entry->update(['ended_at' => CarbonImmutable::now()]);
            if ($task->status === TaskStatus::InProgress) {
                $task->update(['status' => TaskStatus::Paused]);
            }
            DemandEvent::create([
                'organization_id' => $task->organization_id,
                'demand_id' => $task->demand_id,
                'task_id' => $task->id,
                'actor_id' => $user->id,
                'event_type' => 'timer_paused',
                'summary' => $user->name.' pausou o cronômetro de "'.$task->title.'"',
            ]);

            return null;
        });

        if ($result) {
            return $this->actionFailure($request, 'timer', $result, 409);
        }

        $entry = TaskTimeEntry::query()
            ->where('user_id', $user->id)
            ->where('task_id', $task->id)
            ->latest('started_at')
            ->first();

        return $this->actionSuccess($request, $task->fresh(), 'Cronômetro pausado e tempo salvo.', $entry);
    }

    public function recoverTimer(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::Professional, 403);

        $result = DB::transaction(function () use ($user): ?string {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $entry = TaskTimeEntry::query()
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->with('task:id,organization_id,demand_id,title,status')
                ->first();

            if (! $entry) {
                return 'Não há cronômetro ativo para recuperar.';
            }

            $task = $entry->task;
            $entry->update(['ended_at' => CarbonImmutable::now()]);
            if ($task->status === TaskStatus::InProgress) {
                $task->update(['status' => TaskStatus::Paused]);
            }
            DemandEvent::create([
                'organization_id' => $entry->organization_id,
                'demand_id' => $task->demand_id,
                'task_id' => $task->id,
                'actor_id' => $user->id,
                'event_type' => 'timer_recovered',
                'summary' => $user->name.' recuperou e encerrou o cronômetro de "'.$task->title.'". O período offline não foi removido do intervalo já registrado.',
            ]);

            return null;
        });

        if ($result) {
            return $this->actionFailure($request, 'timer', $result, 409);
        }

        $entry = TaskTimeEntry::query()
            ->where('user_id', $user->id)
            ->latest('started_at')
            ->first();

        return $this->actionSuccess($request, $entry->task, 'Cronômetro encerrado agora e tarefa pausada. O intervalo anterior permanece registrado; revise o tempo se o fechamento foi abrupto.', $entry);
    }

    public function heartbeat(Request $request, TaskTimerHeartbeat $heartbeat): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Professional, 403);

        return response()->json(['data' => ['active' => $heartbeat->touch($request->user())]]);
    }

    public function store(Request $request, Demand $demand, TaskAssignmentNotifier $taskAssignmentNotifier): RedirectResponse|JsonResponse
    {
        $this->authorize('manage', $demand);
        if (in_array($demand->status, [DemandStatus::ClientApproval, DemandStatus::Delivery, DemandStatus::Completed], true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Não é possível adicionar tarefas nesta etapa da demanda.',
                    'errors' => ['title' => ['Não é possível adicionar tarefas nesta etapa da demanda.']],
                ], 409);
            }

            return back()->withErrors(['title' => 'Não é possível adicionar tarefas nesta etapa da demanda.']);
        }
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'assignee_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('organization_id', $demand->organization_id)
                    ->where('role', UserRole::Professional->value)
                    ->where('is_active', true)),
            ],
            'estimate_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $task = DB::transaction(function () use ($data, $demand, $request, $taskAssignmentNotifier): DemandTask {
            $task = $demand->tasks()->create([
                'organization_id' => $demand->organization_id,
                'created_by' => $request->user()->id,
                'assigned_to' => $data['assignee_id'],
                'title' => $data['title'],
                'status' => TaskStatus::Todo,
                'estimate_minutes' => $data['estimate_minutes'] ?? null,
            ]);

            DemandEvent::create([
                'organization_id' => $demand->organization_id,
                'demand_id' => $demand->id,
                'task_id' => $task->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'task_assigned',
                'summary' => 'Tarefa "'.$task->title.'" atribuída a '.$task->assignee()->value('name'),
            ]);
            $taskAssignmentNotifier->notify($task, $request->user());

            return $task;
        });

        if ($request->expectsJson()) {
            $task->load('assignee:id,name');

            return response()->json([
                'message' => 'Tarefa adicionada à demanda.',
                'data' => [
                    'id' => $task->id,
                    'demand_id' => $task->demand_id,
                    'title' => $task->title,
                    'status' => $task->status->value,
                    'estimate_minutes' => $task->estimate_minutes,
                    'assignee' => ['id' => $task->assignee->id, 'name' => $task->assignee->name],
                    'assigned_by' => ['id' => $request->user()->id, 'name' => $request->user()->name],
                ],
            ], 201);
        }

        return back()->with('success', 'Tarefa adicionada à demanda.');
    }

    public function updateStatus(Request $request, DemandTask $task): RedirectResponse|JsonResponse
    {
        $this->authorize('updateStatus', $task);
        $data = $request->validate(['status' => ['required', Rule::enum(TaskStatus::class)]]);
        $from = $task->status;
        $to = TaskStatus::from($data['status']);

        if (! in_array($to, $from->next(), true)) {
            return $this->actionFailure($request, 'status', 'Essa mudança de status não é permitida.');
        }

        if ($to === TaskStatus::InProgress) {
            return $this->actionFailure($request, 'status', 'Para iniciar a tarefa e contar o tempo, use “Iniciar tempo”.');
        }

        DB::transaction(function () use ($task, $from, $to, $request): void {
            if (in_array($to, [TaskStatus::Paused, TaskStatus::Blocked, TaskStatus::Completed], true)) {
                User::query()->whereKey($task->assigned_to)->lockForUpdate()->firstOrFail();
                TaskTimeEntry::query()
                    ->where('user_id', $task->assigned_to)
                    ->where('task_id', $task->id)
                    ->whereNull('ended_at')
                    ->update(['ended_at' => CarbonImmutable::now()]);
            }
            $task->update([
                'status' => $to,
                'completed_at' => $to === TaskStatus::Completed ? CarbonImmutable::now() : null,
            ]);
            if ($to === TaskStatus::Completed) {
                DemandTaskAssignment::query()
                    ->where('demand_task_id', $task->id)
                    ->where('professional_id', $task->assigned_to)
                    ->whereNull('released_at')
                    ->update(['completed_at' => CarbonImmutable::now()]);
            }
            DemandEvent::create([
                'organization_id' => $task->organization_id,
                'demand_id' => $task->demand_id,
                'task_id' => $task->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'task_status_changed',
                'summary' => $request->user()->name.' alterou a tarefa "'.$task->title.'" para '.$to->label(),
                'from_status' => $from->value,
                'to_status' => $to->value,
            ]);
        });

        return $this->actionSuccess($request, $task->fresh(), 'Status da tarefa atualizado.');
    }

    public function updateSchedule(Request $request, DemandTask $task): RedirectResponse|JsonResponse
    {
        $this->authorize('updateSchedule', $task);
        $data = $request->validate([
            'planned_start_on' => ['nullable', 'date_format:Y-m-d'],
            'planned_due_on' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $start = $data['planned_start_on'] ?? null;
        $due = $data['planned_due_on'] ?? null;
        if ($start && $due && $due < $start) {
            if ($request->expectsJson()) {
                return $this->actionFailure($request, 'planned_due_on', 'O prazo precisa ser igual ou posterior ao início.');
            }

            return back()->withErrors(['planned_due_on' => 'O prazo precisa ser igual ou posterior ao início.'])->withInput();
        }
        $oldStart = $task->planned_start_on?->format('Y-m-d');
        $oldDue = $task->planned_due_on?->format('Y-m-d');

        if ($oldStart === $start && $oldDue === $due) {
            return $this->actionSuccess($request, $task, 'O cronograma já estava atualizado.');
        }

        DB::transaction(function () use ($task, $request, $start, $due): void {
            $task->update(['planned_start_on' => $start, 'planned_due_on' => $due]);
            $format = fn (?string $date): string => $date ? CarbonImmutable::parse($date)->format('d/m/Y') : 'sem data';
            DemandEvent::create([
                'organization_id' => $task->organization_id,
                'demand_id' => $task->demand_id,
                'task_id' => $task->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'task_schedule_updated',
                'summary' => $request->user()->name.' atualizou as datas de "'.$task->title.'": '.$format($start).' a '.$format($due),
            ]);
        });

        $task->refresh();

        return $this->actionSuccess($request, $task, 'Datas planejadas salvas no cronograma.');
    }

    public function updateAssignee(Request $request, DemandTask $task, TaskAssignmentNotifier $taskAssignmentNotifier): RedirectResponse|JsonResponse
    {
        $this->authorize('updateAssignee', $task);
        $organizationId = $request->user()->organization_id;
        $data = $request->validate([
            'assignee_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('organization_id', $organizationId)
                    ->where('role', UserRole::Professional->value)
                    ->where('is_active', true)),
            ],
        ]);

        $result = DB::transaction(function () use ($data, $organizationId, $request, $task, $taskAssignmentNotifier): ?string {
            $lockedTask = DemandTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
            if ($lockedTask->status === TaskStatus::Completed) {
                return 'Tarefas concluídas mantêm o responsável do registro histórico.';
            }

            $nextAssignee = User::query()
                ->where('organization_id', $organizationId)
                ->where('role', UserRole::Professional->value)
                ->where('is_active', true)
                ->whereKey($data['assignee_id'])
                ->lockForUpdate()
                ->first();

            if (! $nextAssignee) {
                return 'Escolha uma pessoa ativa da equipe desta organização.';
            }

            if ((int) $lockedTask->assigned_to === (int) $nextAssignee->id) {
                return null;
            }

            $previousAssignee = User::query()->whereKey($lockedTask->assigned_to)->firstOrFail();
            $actor = $request->user();
            $now = CarbonImmutable::now();
            $activeEntries = TaskTimeEntry::query()
                ->where('task_id', $lockedTask->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->get();

            foreach ($activeEntries as $entry) {
                $entry->update(['ended_at' => $now]);
            }

            if ($lockedTask->status === TaskStatus::InProgress) {
                $lockedTask->update(['status' => TaskStatus::Paused]);
                DemandEvent::create([
                    'organization_id' => $lockedTask->organization_id,
                    'demand_id' => $lockedTask->demand_id,
                    'task_id' => $lockedTask->id,
                    'actor_id' => $actor->id,
                    'event_type' => 'task_status_changed',
                    'summary' => $actor->name.' pausou "'.$lockedTask->title.'" para transferir de '.$previousAssignee->name.' para '.$nextAssignee->name,
                    'from_status' => TaskStatus::InProgress->value,
                    'to_status' => TaskStatus::Paused->value,
                ]);
            }

            if ($activeEntries->isNotEmpty()) {
                DemandEvent::create([
                    'organization_id' => $lockedTask->organization_id,
                    'demand_id' => $lockedTask->demand_id,
                    'task_id' => $lockedTask->id,
                    'actor_id' => $actor->id,
                    'event_type' => 'timer_paused',
                    'summary' => $actor->name.' encerrou o cronômetro de '.$previousAssignee->name.' ao transferir "'.$lockedTask->title.'"',
                ]);
            }

            $lockedTask->update(['assigned_to' => $nextAssignee->id]);
            DemandTaskAssignment::query()
                ->where('demand_task_id', $lockedTask->id)
                ->where('professional_id', $previousAssignee->id)
                ->whereNull('released_at')
                ->update(['released_at' => $now]);
            DemandTaskAssignment::query()->create([
                'organization_id' => $lockedTask->organization_id,
                'demand_task_id' => $lockedTask->id,
                'professional_id' => $nextAssignee->id,
                'assigned_by' => $actor->id,
                'assigned_at' => $now,
            ]);
            DemandEvent::create([
                'organization_id' => $lockedTask->organization_id,
                'demand_id' => $lockedTask->demand_id,
                'task_id' => $lockedTask->id,
                'actor_id' => $actor->id,
                'event_type' => 'task_reassigned',
                'summary' => $actor->name.' reatribuiu "'.$lockedTask->title.'" de '.$previousAssignee->name.' para '.$nextAssignee->name,
            ]);
            $taskAssignmentNotifier->notify($lockedTask, $actor, reassigned: true);

            return null;
        });

        if ($result) {
            return $this->actionFailure($request, 'assignee_id', $result, 409);
        }

        return $this->actionSuccess($request, $task->fresh(), 'Responsável atualizado. O histórico mantém quem executou cada etapa.');
    }

    private function actionSuccess(Request $request, DemandTask $task, string $message, ?TaskTimeEntry $entry = null): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return back()->with('success', $message);
        }

        $task->loadMissing(['assignee:id,name', 'creator:id,name', 'latestAssignmentEvent.actor:id,name']);

        return response()->json([
            'message' => $message,
            'data' => [
                'task_id' => $task->id,
                'status' => $task->status->value,
                'status_label' => $task->status->label(),
                'completed_at' => $task->completed_at?->toISOString(),
                'assignee' => ['id' => $task->assignee->id, 'name' => $task->assignee->name],
                'assigned_by' => $task->latestAssignmentEvent?->actor?->only(['id', 'name']) ?? ['id' => $task->creator->id, 'name' => $task->creator->name],
                'planned_start_on' => $task->planned_start_on?->format('Y-m-d'),
                'planned_due_on' => $task->planned_due_on?->format('Y-m-d'),
                'timer' => $entry ? [
                    'id' => $entry->id,
                    'started_at' => $entry->started_at?->toISOString(),
                    'last_heartbeat_at' => $entry->last_heartbeat_at?->toISOString(),
                    'ended_at' => $entry->ended_at?->toISOString(),
                    'duration_seconds' => $entry->ended_at
                        ? max(0, $entry->started_at->diffInSeconds($entry->ended_at, false))
                        : null,
                ] : null,
            ],
        ]);
    }

    private function actionFailure(Request $request, string $field, string $message, int $status = 422): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return back()->withErrors([$field => $message]);
        }

        return response()->json([
            'message' => $message,
            'errors' => [$field => [$message]],
        ], $status);
    }
}
