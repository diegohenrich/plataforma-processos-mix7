<?php

namespace App\Http\Controllers;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandEvent;
use App\Models\DemandTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DemandTaskController extends Controller
{
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

        DB::transaction(function () use ($task, $from, $to, $request): void {
            $task->update(['status' => $to]);
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
}
