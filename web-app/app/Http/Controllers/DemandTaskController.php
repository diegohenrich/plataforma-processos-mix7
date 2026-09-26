<?php

namespace App\Http\Controllers;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandEvent;
use App\Models\DemandTask;
use App\Models\TaskTimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DemandTaskController extends Controller
{
    public function startTimer(Request $request, DemandTask $task): RedirectResponse
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
            TaskTimeEntry::create([
                'organization_id' => $task->organization_id,
                'task_id' => $task->id,
                'user_id' => $user->id,
                'started_at' => $now,
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

        return $result
            ? back()->withErrors(['timer' => $result])
            : back()->with('success', 'Cronômetro iniciado. O tempo será salvo nesta tarefa.');
    }

    public function pauseTimer(Request $request, DemandTask $task): RedirectResponse
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

        return $result
            ? back()->withErrors(['timer' => $result])
            : back()->with('success', 'Cronômetro pausado e tempo salvo.');
    }

    public function store(Request $request, Demand $demand): RedirectResponse
    {
        $this->authorize('manage', $demand);
        if (in_array($demand->status, [DemandStatus::ClientApproval, DemandStatus::Delivery, DemandStatus::Completed], true)) {
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

        DB::transaction(function () use ($data, $demand, $request): void {
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
        });

        return back()->with('success', 'Tarefa adicionada à demanda.');
    }

    public function updateStatus(Request $request, DemandTask $task): RedirectResponse
    {
        $this->authorize('updateStatus', $task);
        $data = $request->validate(['status' => ['required', Rule::enum(TaskStatus::class)]]);
        $from = $task->status;
        $to = TaskStatus::from($data['status']);

        if (! in_array($to, $from->next(), true)) {
            return back()->withErrors(['status' => 'Essa mudança de status não é permitida.']);
        }

        if ($to === TaskStatus::InProgress && $task->dependencies()->where('status', '!=', TaskStatus::Completed->value)->exists()) {
            return back()->withErrors(['status' => 'Conclua as tarefas anteriores antes de iniciar esta tarefa.']);
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

        return back()->with('success', 'Status da tarefa atualizado.');
    }

    public function updateSchedule(Request $request, DemandTask $task): RedirectResponse
    {
        $this->authorize('updateSchedule', $task);
        $data = $request->validate([
            'planned_start_on' => ['nullable', 'date_format:Y-m-d'],
            'planned_due_on' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $start = $data['planned_start_on'] ?? null;
        $due = $data['planned_due_on'] ?? null;
        if ($start && $due && $due < $start) {
            return back()->withErrors(['planned_due_on' => 'O prazo precisa ser igual ou posterior ao início.'])->withInput();
        }
        $oldStart = $task->planned_start_on?->format('Y-m-d');
        $oldDue = $task->planned_due_on?->format('Y-m-d');

        if ($oldStart === $start && $oldDue === $due) {
            return back()->with('success', 'O cronograma já estava atualizado.');
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

        return back()->with('success', 'Datas planejadas salvas no cronograma.');
    }
}
