<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Demand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Demand::class);
        $user = $request->user();
        $demands = Demand::query()
            ->where('organization_id', $user->organization_id)
            ->when($user->role === UserRole::Professional, function (Builder $query) use ($user): void {
                $query->where(function (Builder $visible) use ($user): void {
                    $visible->where('created_by', $user->id)
                        ->orWhere('responsible_user_id', $user->id)
                        ->orWhereHas('tasks', fn (Builder $tasks) => $tasks->where('assigned_to', $user->id));
                });
            })
            ->when($user->role === UserRole::Client, fn (Builder $query) => $query->where('client_user_id', $user->id))
            ->with([
                'creator:id,name',
                'briefAuthor:id,name',
                'responsible:id,name',
                'moduleSteps.completer:id,name',
                'tasks' => fn ($tasks) => $tasks
                    ->with(['assignee:id,name', 'creator:id,name', 'latestAssignmentEvent.actor:id,name'])
                    ->when($user->role === UserRole::Professional, fn (Builder $query) => $query->where('assigned_to', $user->id))
                    ->when($user->role === UserRole::Client, fn (Builder $query) => $query->whereRaw('1 = 0')),
            ])
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $demands->map(fn (Demand $demand) => $user->role === UserRole::Client
                ? [
                    'id' => $demand->id,
                    'title' => $demand->title,
                    'module' => $this->moduleData($demand),
                    'status' => ['value' => $demand->status->value, 'label' => $demand->status->label()],
                    'updated_at' => $demand->updated_at?->toISOString(),
                ]
                : [
                    'id' => $demand->id,
                    'title' => $demand->title,
                    'brief' => $demand->brief,
                    'brief_author' => $demand->briefAuthor?->only(['id', 'name']),
                    'responsible' => $demand->responsible?->only(['id', 'name']),
                    'materials_location' => $demand->materials_location,
                    'access_instructions' => $demand->access_instructions,
                    'summary' => $demand->ai_summary,
                    'suggested_solution' => $demand->suggested_solution,
                    'module' => $this->moduleData($demand),
                    'module_fields' => ['schema' => $demand->module_fields_schema ?? [], 'data' => $demand->module_fields_data ?? []],
                    'module_steps' => $this->moduleStepsData($demand),
                    'status' => ['value' => $demand->status->value, 'label' => $demand->status->label()],
                    'created_at' => $demand->created_at?->toISOString(),
                    'created_by' => ['id' => $demand->creator->id, 'name' => $demand->creator->name],
                    'tasks' => $demand->tasks
                        ->map(fn ($task) => [
                            'id' => $task->id,
                            'title' => $task->title,
                            'status' => ['value' => $task->status->value, 'label' => $task->status->label()],
                            'estimate_minutes' => $task->estimate_minutes,
                            'assignee' => ['id' => $task->assignee->id, 'name' => $task->assignee->name],
                            'assigned_by' => $task->latestAssignmentEvent?->actor?->only(['id', 'name']) ?? ['id' => $task->creator->id, 'name' => $task->creator->name],
                        ]),
                ]),
            'meta' => ['limit' => 100],
        ]);
    }

    public function show(Request $request, Demand $demand): JsonResponse
    {
        $this->authorize('view', $demand);
        $user = $request->user();
        if ($user->role === UserRole::Client) {
            return response()->json(['data' => [
                'id' => $demand->id,
                'title' => $demand->title,
                'module' => $this->moduleData($demand),
                'status' => ['value' => $demand->status->value, 'label' => $demand->status->label()],
                'updated_at' => $demand->updated_at?->toISOString(),
            ]]);
        }
        $tasks = $demand->tasks()
            ->with(['assignee:id,name', 'creator:id,name', 'latestAssignmentEvent.actor:id,name'])
            ->when($user->role === UserRole::Professional, fn (Builder $query) => $query->where('assigned_to', $user->id))
            ->get();

        return response()->json([
            'data' => [
                'id' => $demand->id,
                'title' => $demand->title,
                'brief' => $demand->brief,
                'brief_author' => $demand->briefAuthor()->first(['id', 'name'])?->only(['id', 'name']),
                'responsible' => $demand->responsible()->first(['id', 'name'])?->only(['id', 'name']),
                'materials_location' => $demand->materials_location,
                'access_instructions' => $demand->access_instructions,
                'summary' => $demand->ai_summary,
                'suggested_solution' => $demand->suggested_solution,
                'module' => $this->moduleData($demand),
                'module_fields' => ['schema' => $demand->module_fields_schema ?? [], 'data' => $demand->module_fields_data ?? []],
                'module_steps' => $this->moduleStepsData($demand),
                'status' => ['value' => $demand->status->value, 'label' => $demand->status->label()],
                'tasks' => $tasks->map(fn ($task) => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => ['value' => $task->status->value, 'label' => $task->status->label()],
                    'estimate_minutes' => $task->estimate_minutes,
                    'assignee' => ['id' => $task->assignee->id, 'name' => $task->assignee->name],
                    'assigned_by' => $task->latestAssignmentEvent?->actor?->only(['id', 'name']) ?? ['id' => $task->creator->id, 'name' => $task->creator->name],
                ]),
            ],
        ]);
    }

    private function moduleData(Demand $demand): ?array
    {
        if (! $demand->module_key) {
            return null;
        }

        return ['key' => $demand->module_key, 'label' => $demand->moduleDisplayLabel(), 'version' => $demand->module_version];
    }

    private function moduleStepsData(Demand $demand): array
    {
        return $demand->moduleSteps()->with('completer:id,name')->get()->map(fn ($step): array => [
            'key' => $step->key,
            'label' => $step->label,
            'position' => $step->position,
            'completed' => $step->completed_at !== null,
            'completed_at' => $step->completed_at?->toISOString(),
            'completed_by' => $step->completer ? ['id' => $step->completer->id, 'name' => $step->completer->name] : null,
        ])->all();
    }
}
