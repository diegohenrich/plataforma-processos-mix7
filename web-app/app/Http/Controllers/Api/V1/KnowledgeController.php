<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\KnowledgeItem;
use App\Models\OnboardingAssignment;
use App\Models\OnboardingAssignmentStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class KnowledgeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('manageAny', KnowledgeItem::class);
        $data = $this->validateItem($request);
        $steps = $this->normalizeSteps($data['steps'] ?? []);
        abort_if($data['type'] === 'onboarding' && $steps === [], 422, 'Inclua ao menos uma etapa para a trilha.');

        $item = KnowledgeItem::create($this->itemAttributes($data, $steps, $request));

        return response()->json(['data' => $this->serializeItem($item)], 201);
    }

    public function update(Request $request, KnowledgeItem $item): JsonResponse
    {
        Gate::authorize('manage', $item);
        abort_if($item->archived_at, 404);
        $data = $this->validateItem($request);
        $steps = $this->normalizeSteps($data['steps'] ?? []);
        abort_if($data['type'] === 'onboarding' && $steps === [], 422, 'Inclua ao menos uma etapa para a trilha.');
        $attributes = $this->itemAttributes($data, $steps, $request);
        unset($attributes['organization_id'], $attributes['created_by']);
        $item->update($attributes);

        return response()->json(['data' => $this->serializeItem($item->fresh())]);
    }

    public function archive(Request $request, KnowledgeItem $item): JsonResponse
    {
        Gate::authorize('manage', $item);
        abort_if($item->archived_at, 404);
        $item->update(['archived_at' => now(), 'updated_by' => $request->user()->id]);

        return response()->json(['data' => ['id' => $item->id, 'archived_at' => $item->fresh()->archived_at?->toISOString()]]);
    }

    public function restore(Request $request, KnowledgeItem $item): JsonResponse
    {
        Gate::authorize('manage', $item);
        abort_unless($item->archived_at, 404);
        $item->update(['archived_at' => null, 'updated_by' => $request->user()->id]);

        return response()->json(['data' => $this->serializeItem($item->fresh())]);
    }

    public function assign(Request $request, KnowledgeItem $item): JsonResponse
    {
        Gate::authorize('manage', $item);
        abort_if($item->archived_at, 404);
        abort_unless($item->type === 'onboarding' && is_array($item->steps) && count($item->steps) > 0, 422);
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::unique('onboarding_assignments', 'assigned_to')->where(fn ($query) => $query->where('knowledge_item_id', $item->id)), Rule::exists('users', 'id')->where(fn ($query) => $query->where('organization_id', $request->user()->organization_id)->where('role', UserRole::Professional->value)->where('is_active', true))],
        ]);
        $assignment = DB::transaction(function () use ($item, $data, $request): OnboardingAssignment {
            $assignment = OnboardingAssignment::create(['organization_id' => $request->user()->organization_id, 'knowledge_item_id' => $item->id, 'assigned_to' => $data['user_id'], 'assigned_by' => $request->user()->id, 'title' => $item->title, 'steps' => $item->steps]);
            foreach ($item->steps as $position => $title) {
                $assignment->steps()->create(['position' => $position + 1, 'title' => $title]);
            }

            return $assignment;
        });

        return response()->json(['data' => ['id' => $assignment->id, 'knowledge_item_id' => $assignment->knowledge_item_id, 'assigned_to' => $assignment->assigned_to, 'assigned_by' => $assignment->assigned_by, 'title' => $assignment->title, 'total_steps' => $assignment->steps()->count()]], 201);
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', KnowledgeItem::class);
        $user = $request->user();
        $items = KnowledgeItem::query()
            ->where('organization_id', $user->organization_id)
            ->whereNull('archived_at')
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($request->string('q')->toString())).'%';
                $query->where(fn ($search) => $search->where('title', 'like', $term)->orWhere('content', 'like', $term));
            })
            ->orderBy('type')->orderBy('title')->limit(100)->get();

        return response()->json(['data' => $items->map(fn (KnowledgeItem $item) => [
            'id' => $item->id,
            'type' => $item->type,
            'title' => $item->title,
            'content' => $item->content,
            'owner_name' => $item->owner_name,
            'audience' => $item->audience,
            'review_due_at' => $item->review_due_at?->toDateString(),
            'url' => $item->url,
            'steps' => $item->steps,
            'updated_at' => $item->updated_at?->toISOString(),
        ])]);
    }

    public function assignments(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', KnowledgeItem::class);
        $user = $request->user();
        abort_unless($user->role === UserRole::Professional, 403);

        $assignments = OnboardingAssignment::query()
            ->where('organization_id', $user->organization_id)
            ->where('assigned_to', $user->id)
            ->with('steps')
            ->latest()
            ->limit(100)
            ->get();

        return response()->json(['data' => $assignments->map(function (OnboardingAssignment $assignment): array {
            $steps = $assignment->getRelation('steps');

            return [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'steps' => $steps->map(fn (OnboardingAssignmentStep $step) => [
                    'id' => $step->id,
                    'position' => $step->position,
                    'title' => $step->title,
                    'completed_at' => $step->completed_at?->toISOString(),
                ]),
                'completed_steps' => $steps->whereNotNull('completed_at')->count(),
                'total_steps' => $steps->count(),
            ];
        })]);
    }

    public function toggleStep(Request $request, OnboardingAssignment $assignment, OnboardingAssignmentStep $step): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === UserRole::Professional, 403);
        abort_unless($assignment->organization_id === $user->organization_id && $assignment->assigned_to === $user->id && $step->assignment_id === $assignment->id, 404);

        $done = $step->completed_at === null;
        $step->update(['completed_by' => $done ? $user->id : null, 'completed_at' => $done ? now() : null]);

        return response()->json(['data' => [
            'id' => $step->id,
            'completed_at' => $step->fresh()->completed_at?->toISOString(),
        ]]);
    }

    private function validateItem(Request $request): array
    {
        return $request->validate(['type' => ['required', Rule::in(['reference', 'training', 'contact', 'onboarding'])], 'title' => ['required', 'string', 'max:180'], 'content' => ['required', 'string', 'max:12000'], 'owner_name' => ['nullable', 'string', 'max:160'], 'audience' => ['nullable', 'string', 'max:160'], 'review_due_at' => ['nullable', 'date'], 'url' => ['nullable', 'url:http,https', 'max:2048'], 'steps' => ['nullable', 'array', 'max:30'], 'steps.*' => ['nullable', 'string', 'max:180']]);
    }

    private function normalizeSteps(array $steps): array
    {
        return array_values(array_filter(array_map(fn ($step) => trim((string) $step), $steps), fn ($step) => $step !== ''));
    }

    private function itemAttributes(array $data, array $steps, Request $request): array
    {
        return ['organization_id' => $request->user()->organization_id, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id, 'type' => $data['type'], 'title' => trim($data['title']), 'content' => trim($data['content']), 'owner_name' => isset($data['owner_name']) ? trim($data['owner_name']) : null, 'audience' => isset($data['audience']) ? trim($data['audience']) : null, 'review_due_at' => $data['review_due_at'] ?? null, 'url' => isset($data['url']) ? trim($data['url']) : null, 'steps' => in_array($data['type'], ['training', 'onboarding'], true) ? $steps : null];
    }

    private function serializeItem(KnowledgeItem $item): array
    {
        return ['id' => $item->id, 'type' => $item->type, 'title' => $item->title, 'content' => $item->content, 'owner_name' => $item->owner_name, 'audience' => $item->audience, 'review_due_at' => $item->review_due_at?->toDateString(), 'url' => $item->url, 'steps' => $item->steps, 'updated_at' => $item->updated_at?->toISOString()];
    }
}
