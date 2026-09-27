<?php

namespace App\Http\Controllers;

use App\Enums\DemandModule;
use App\Enums\DemandStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Demand;
use App\Models\DemandEvent;
use App\Models\DemandModuleDefinition;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
        $briefAuthors = User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->whereIn('role', [UserRole::AgencyOwner->value, UserRole::MarketingManager->value, UserRole::Professional->value])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $modules = collect(DemandModule::cases())->map(fn (DemandModule $module): array => [
            'key' => $module->value,
            'label' => $module->label(),
            'fields' => [],
        ])->concat(DemandModuleDefinition::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('is_active', true)
            ->orderBy('label')
            ->get(['key', 'label', 'fields', 'workflow_steps'])
            ->map(fn (DemandModuleDefinition $module): array => ['key' => $module->key, 'label' => $module->label, 'fields' => $module->fields ?? [], 'workflow_steps' => $module->workflow_steps ?? []]));

        return view('demands.create', compact('professionals', 'clients', 'briefAuthors', 'modules'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Demand::class);
        $organizationId = $request->user()->organization_id;
        $allowedModuleKeys = array_merge(
            array_map(fn (DemandModule $module): string => $module->value, DemandModule::cases()),
            DemandModuleDefinition::query()->where('organization_id', $organizationId)->where('is_active', true)->pluck('key')->all(),
        );
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'brief' => ['required', 'string', 'max:12000'],
            'intake_source' => ['nullable', 'string', 'max:120'],
            'brief_author_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('organization_id', $organizationId)
                    ->whereIn('role', [UserRole::AgencyOwner->value, UserRole::MarketingManager->value, UserRole::Professional->value])
                    ->where('is_active', true)),
            ],
            'module_key' => ['required', 'string', Rule::in($allowedModuleKeys)],
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
            $builtInModule = DemandModule::tryFrom($data['module_key']);
            $customModule = $builtInModule ? null : DemandModuleDefinition::query()
                ->where('organization_id', $organizationId)
                ->where('key', $data['module_key'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $moduleFields = $customModule?->fields ?? [];
            $moduleSteps = $customModule?->workflow_steps ?? [];
            $moduleFieldsData = $this->validateModuleFields($request, $moduleFields);
            $demand = Demand::create([
                'organization_id' => $organizationId,
                'created_by' => $request->user()->id,
                'client_user_id' => $data['client_user_id'] ?? null,
                'title' => $data['title'],
                'brief' => $data['brief'],
                'intake_source' => isset($data['intake_source']) ? trim($data['intake_source']) : null,
                'brief_author_id' => $data['brief_author_id'] ?? null,
                'module_key' => $data['module_key'],
                'module_version' => $builtInModule?->version() ?? $customModule->config_version,
                'module_label' => $builtInModule?->label() ?? $customModule->label,
                'module_fields_schema' => $moduleFields,
                'module_fields_data' => $moduleFieldsData,
                'status' => DemandStatus::Received,
            ]);

            foreach ($moduleSteps as $position => $step) {
                $demand->moduleSteps()->create([
                    'organization_id' => $organizationId,
                    'key' => $step['key'],
                    'label' => $step['label'],
                    'position' => $position,
                ]);
            }

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

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Demanda criada e tarefas atribuídas.',
                'data' => [
                    'id' => $demand->id,
                    'title' => $demand->title,
                    'intake_source' => $demand->intake_source,
                    'brief_author' => $demand->briefAuthor()->first(['id', 'name'])?->only(['id', 'name']),
                    'module' => ['key' => $demand->module_key, 'label' => $demand->moduleDisplayLabel(), 'version' => $demand->module_version],
                    'module_fields' => ['schema' => $demand->module_fields_schema ?? [], 'data' => $demand->module_fields_data ?? []],
                    'module_steps' => $demand->moduleSteps()->get(['key', 'label', 'position'])->map(fn ($step): array => ['key' => $step->key, 'label' => $step->label, 'position' => $step->position, 'completed' => false]),
                    'status' => $demand->status->value,
                    'status_label' => $demand->status->label(),
                    'client_user_id' => $demand->client_user_id,
                    'tasks' => $demand->tasks()->with('assignee:id,name')->get()->map(fn ($task) => [
                        'id' => $task->id,
                        'title' => $task->title,
                        'status' => $task->status->value,
                        'estimate_minutes' => $task->estimate_minutes,
                        'assignee' => ['id' => $task->assignee->id, 'name' => $task->assignee->name],
                        'assigned_by' => ['id' => $request->user()->id, 'name' => $request->user()->name],
                    ]),
                ],
            ], 201);
        }

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
            ->with(['creator:id,name', 'assignee:id,name,is_active', 'latestAssignmentEvent.actor:id,name', 'timeEntries', 'dependencies:id,title,status'])
            ->when($user->role === UserRole::Professional, fn (Builder $query) => $query->where('assigned_to', $user->id))
            ->get();
        $reviewLinks = $request->user()->can('manage', $demand)
            ? $demand->reviewLinks()->with('responses')->get()
            : collect();
        $feedbackById = [];
        foreach ($reviewLinks as $link) {
            foreach ($link->responses as $response) {
                $feedbackById[$response->id] = [
                    'version' => $link->version,
                    'type' => $response->type,
                    'comment' => $response->comment,
                    'anchor_type' => $response->anchor_type,
                    'anchor_data' => $response->anchor_data ?? [],
                ];
            }
        }

        return view('demands.show', [
            'currentUser' => $user,
            'demand' => $demand->load(['creator:id,name', 'briefAuthor:id,name', 'organization:id,name', 'client:id,name,email', 'attachments.uploader:id,name']),
            'moduleSteps' => $demand->moduleSteps()->with('completer:id,name')->get(),
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
                ? User::query()->where('organization_id', $user->organization_id)->where('role', UserRole::Professional->value)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'specialties'])
                : collect(),
            'clients' => $request->user()->can('manage', $demand)
                ? User::query()->where('organization_id', $user->organization_id)->where('role', UserRole::Client->value)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email'])
                : collect(),
            'nextStatuses' => $demand->status->next(),
            'activeTimeTaskId' => $user->activeTimeEntry()->value('task_id'),
            'reviewLinks' => $reviewLinks,
            'feedbackById' => $feedbackById,
            'deliveryEvidences' => $demand->deliveryEvidences()->with('recorder:id,name')->get(),
            'aiPlanningRuns' => $request->user()->can('manage', $demand) ? $demand->aiPlanningRuns()->with(['requester:id,name', 'reviewer:id,name'])->take(5)->get() : collect(),
            'aiAgentRuns' => $demand->aiAgentRuns()
                ->with('requester:id,name')
                ->where('requested_by', $request->user()->id)
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

    public function updateStatus(Request $request, Demand $demand): RedirectResponse|JsonResponse
    {
        $this->authorize('manage', $demand);
        $data = $request->validate([
            'status' => ['required', Rule::enum(DemandStatus::class)],
        ]);
        $from = $demand->status;
        $to = DemandStatus::from($data['status']);

        if ($from === DemandStatus::ClientApproval) {
            return $this->workflowFailure($request, 'A etapa só avança depois que o cliente registra uma decisão pelo link de revisão.');
        }

        if (! in_array($to, $from->next(), true)) {
            return $this->workflowFailure($request, 'Essa etapa não pode vir depois do estado atual.');
        }

        if ($to === DemandStatus::InternalReview && $demand->tasks()->where('status', '!=', TaskStatus::Completed->value)->exists()) {
            return $this->workflowFailure($request, 'Conclua todas as tarefas antes da revisão interna.');
        }

        DB::transaction(function () use ($demand, $from, $to, $request): void {
            $demand->update(['status' => $to]);
            $actor = $request->user()->name;
            $summary = match (true) {
                $from === DemandStatus::InternalReview && $to === DemandStatus::ClientApproval => $actor.' aprovou a revisão interna e enviou a demanda para aprovação do cliente',
                $from === DemandStatus::InternalReview && $to === DemandStatus::InProgress => $actor.' devolveu a demanda para execução após a revisão interna',
                default => $actor.' alterou a etapa para '.$to->label(),
            };
            DemandEvent::create([
                'organization_id' => $demand->organization_id,
                'demand_id' => $demand->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'demand_status_changed',
                'summary' => $summary,
                'from_status' => $from->value,
                'to_status' => $to->value,
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Etapa da demanda atualizada.',
                'data' => [
                    'id' => $demand->id,
                    'status' => $demand->fresh()->status->value,
                    'status_label' => $demand->fresh()->status->label(),
                ],
            ]);
        }

        return back()->with('success', 'Etapa da demanda atualizada.');
    }

    private function workflowFailure(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return back()->withErrors(['status' => $message]);
        }

        return response()->json(['message' => $message, 'errors' => ['status' => [$message]]], 409);
    }

    private function validateModuleFields(Request $request, array $fields): array
    {
        $input = $request->input('module_fields_data', []);
        if (! is_array($input)) {
            Validator::make(['module_fields_data' => $input], ['module_fields_data' => ['array']])->validate();
        }

        $keys = array_column($fields, 'key');
        if ($keys === [] && $input !== []) {
            Validator::make(['module_fields_data' => $input], ['module_fields_data' => ['array', 'size:0']])->validate();
        }
        $rules = ['module_fields_data' => $keys === [] ? ['array'] : ['array:'.implode(',', $keys)]];
        foreach ($fields as $field) {
            $key = 'module_fields_data.'.$field['key'];
            $typeRules = match ($field['type']) {
                'textarea' => ['string', 'max:4000'],
                'date' => ['date'],
                'url' => ['url', 'max:2048'],
                'select' => [Rule::in($field['options'] ?? [])],
                default => ['string', 'max:1000'],
            };
            $rules[$key] = array_merge([($field['required'] ?? false) ? 'required' : 'nullable'], $typeRules);
        }

        $validated = Validator::make(['module_fields_data' => $input], $rules)->validate();

        return collect($validated['module_fields_data'] ?? [])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }
}
