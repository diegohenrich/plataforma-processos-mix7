<?php

namespace App\Http\Controllers;

use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DemandController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Demand::class);
        $user = $request->user();
        $isBoard = $user->role !== UserRole::Client && $request->query('view') !== 'list';
        $query = Demand::query()
            ->where('organization_id', $user->organization_id)
            ->when($user->role === UserRole::Professional, function (Builder $query) use ($user): void {
                $query->where(function (Builder $visible) use ($user): void {
                    $visible->where('created_by', $user->id)
                        ->orWhereHas('tasks', fn (Builder $tasks) => $tasks->where('assigned_to', $user->id));
                });
            })
            ->when($user->role === UserRole::Client, fn (Builder $query) => $query->where('client_user_id', $user->id))
            ->with([
                'creator:id,name',
                'client:id,name',
                'tasks' => fn ($tasks) => $tasks
                    ->with('assignee:id,name')
                    ->when($user->role === UserRole::Professional, fn (Builder $query) => $query->where('assigned_to', $user->id))
                    ->when($user->role === UserRole::Client, fn (Builder $query) => $query->whereRaw('1 = 0')),
            ])
            ->latest();

        $demands = $isBoard ? $query->get() : $query->paginate(12)->withQueryString();
        $boardColumns = $isBoard
            ? collect(DemandStatus::cases())->mapWithKeys(fn (DemandStatus $status) => [
                $status->value => $demands->where('status', $status)->values(),
            ])
            : collect();

        return view('demands.index', [
            'demands' => $demands,
            'isBoard' => $isBoard,
            'boardColumns' => $boardColumns,
            'canMoveDemands' => $user->can('create', Demand::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Demand::class);
        $professionals = User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('role', UserRole::Professional->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $clients = User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('role', UserRole::Client->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('demands.create', compact('professionals', 'clients'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Demand::class);
        $organizationId = $request->user()->organization_id;
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'brief' => ['required', 'string', 'max:12000'],
            'client_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('organization_id', $organizationId)
                    ->where('role', UserRole::Client->value)
                    ->where('is_active', true)),
            ],
            'tasks' => ['required', 'array', 'min:1', 'max:20'],
            'tasks.*.title' => ['required', 'string', 'max:180'],
            'tasks.*.assignee_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('organization_id', $organizationId)
                    ->where('role', UserRole::Professional->value)
                    ->where('is_active', true)),
            ],
            'tasks.*.estimate_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $demand = DB::transaction(function () use ($data, $request, $organizationId): Demand {
            $demand = Demand::create([
                'organization_id' => $organizationId,
                'created_by' => $request->user()->id,
                'client_user_id' => $data['client_user_id'] ?? null,
                'title' => $data['title'],
                'brief' => $data['brief'],
                'status' => DemandStatus::Received,
            ]);

            if ($demand->client_user_id) {
                DemandEvent::create([
                    'organization_id' => $organizationId,
                    'demand_id' => $demand->id,
                    'actor_id' => $request->user()->id,
                    'event_type' => 'demand_client_assigned',
                    'summary' => 'Demanda vinculada ao cliente '.$demand->client()->value('name'),
                ]);
            }

            DemandEvent::create([
                'organization_id' => $organizationId,
                'demand_id' => $demand->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'demand_created',
                'summary' => 'Demanda criada por '.$request->user()->name,
                'to_status' => DemandStatus::Received->value,
            ]);

            foreach ($data['tasks'] as $taskData) {
                $task = $demand->tasks()->create([
                    'organization_id' => $organizationId,
                    'created_by' => $request->user()->id,
                    'assigned_to' => $taskData['assignee_id'],
                    'title' => $taskData['title'],
                    'status' => TaskStatus::Todo,
                    'estimate_minutes' => $taskData['estimate_minutes'] ?? null,
                ]);

                DemandEvent::create([
                    'organization_id' => $organizationId,
                    'demand_id' => $demand->id,
                    'task_id' => $task->id,
                    'actor_id' => $request->user()->id,
                    'event_type' => 'task_assigned',
                    'summary' => 'Tarefa "'.$task->title.'" atribuída a '.$task->assignee()->value('name'),
                ]);
            }

            return $demand;
        });

        return redirect()->route('demands.show', $demand)->with('success', 'Demanda criada e tarefas atribuídas.');
    }

    public function show(Request $request, Demand $demand): View
    {
        $this->authorize('view', $demand);
        $user = $request->user();
        if ($user->role === UserRole::Client) {
            return view('demands.client-show', ['demand' => $demand]);
        }

        $tasks = $demand->tasks()
            ->with(['creator:id,name', 'assignee:id,name,is_active', 'timeEntries', 'dependencies:id,title,status'])
            ->when($user->role === UserRole::Professional, fn (Builder $query) => $query->where('assigned_to', $user->id))
            ->get();

        return view('demands.show', [
            'currentUser' => $user,
            'demand' => $demand->load(['creator:id,name', 'organization:id,name', 'client:id,name,email']),
            'tasks' => $tasks,
            'scheduledTasks' => $tasks->filter(fn ($task) => $task->planned_start_on || $task->planned_due_on)->values(),
            'unscheduledTaskCount' => $tasks->filter(fn ($task) => ! $task->planned_start_on && ! $task->planned_due_on)->count(),
            'events' => $demand->events()
                ->with('actor:id,name')
                ->when($user->role === UserRole::Professional, fn (Builder $query) => $query->where(fn (Builder $visible) => $visible
                    ->whereNull('task_id')
                    ->orWhereHas('task', fn (Builder $tasks) => $tasks->where('assigned_to', $user->id))))
                ->take(30)
                ->get(),
            'canManage' => $request->user()->can('manage', $demand),
            'professionals' => $request->user()->can('manage', $demand)
                ? User::query()->where('organization_id', $user->organization_id)->where('role', UserRole::Professional->value)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'clients' => $request->user()->can('manage', $demand)
                ? User::query()->where('organization_id', $user->organization_id)->where('role', UserRole::Client->value)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email'])
                : collect(),
            'nextStatuses' => $demand->status->next(),
            'activeTimeTaskId' => $user->activeTimeEntry()->value('task_id'),
            'reviewLinks' => $request->user()->can('manage', $demand) ? $demand->reviewLinks()->with('responses')->get() : collect(),
            'aiPlanningRuns' => $request->user()->can('manage', $demand) ? $demand->aiPlanningRuns()->with(['requester:id,name', 'reviewer:id,name'])->take(5)->get() : collect(),
            'aiAgentRuns' => $demand->aiAgentRuns()
                ->with('requester:id,name')
                ->where(function (Builder $query) use ($request, $demand): void {
                    $query->where('requested_by', $request->user()->id);
                    if ($request->user()->can('manage', $demand)) {
                        $query->orWhere('organization_id', $request->user()->organization_id);
                    }
                })
                ->take(8)
                ->get(),
        ]);
    }

    public function assignClient(Request $request, Demand $demand): RedirectResponse
    {
        $this->authorize('manage', $demand);
        $organizationId = $request->user()->organization_id;
        $data = $request->validate([
            'client_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('organization_id', $organizationId)
                    ->where('role', UserRole::Client->value)
                    ->where('is_active', true)),
            ],
        ]);

        DB::transaction(function () use ($demand, $request, $data, $organizationId): void {
            $locked = Demand::query()->whereKey($demand->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->client_user_id === (int) ($data['client_user_id'] ?? 0)) {
                return;
            }

            $previousClient = $locked->client()->value('name');
            $locked->update(['client_user_id' => $data['client_user_id'] ?? null]);
            $clientName = isset($data['client_user_id']) ? User::query()->whereKey($data['client_user_id'])->value('name') : null;
            DemandEvent::create([
                'organization_id' => $organizationId,
                'demand_id' => $locked->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'demand_client_assigned',
                'summary' => $clientName
                    ? 'Demanda vinculada ao cliente '.$clientName
                    : 'Vínculo do cliente '.($previousClient ?: 'anterior').' removido',
            ]);
        });

        return back()->with('success', 'Vínculo do cliente atualizado.');
    }

    public function updateStatus(Request $request, Demand $demand): RedirectResponse
    {
        $this->authorize('manage', $demand);
        $data = $request->validate([
            'status' => ['required', Rule::enum(DemandStatus::class)],
        ]);
        $from = $demand->status;
        $to = DemandStatus::from($data['status']);

        if (! in_array($to, $from->next(), true)) {
            return back()->withErrors(['status' => 'Essa etapa não pode vir depois do estado atual.']);
        }

        if ($to === DemandStatus::InternalReview && $demand->tasks()->where('status', '!=', TaskStatus::Completed->value)->exists()) {
            return back()->withErrors(['status' => 'Conclua todas as tarefas antes da revisão interna.']);
        }

        DB::transaction(function () use ($demand, $from, $to, $request): void {
            $demand->update(['status' => $to]);
            DemandEvent::create([
                'organization_id' => $demand->organization_id,
                'demand_id' => $demand->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'demand_status_changed',
                'summary' => $request->user()->name.' alterou a etapa para '.$to->label(),
                'from_status' => $from->value,
                'to_status' => $to->value,
            ]);
        });

        return back()->with('success', 'Etapa da demanda atualizada.');
    }
}
