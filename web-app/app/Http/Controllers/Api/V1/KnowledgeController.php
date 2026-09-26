<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\KnowledgeItem;
use App\Models\OnboardingAssignment;
use App\Models\OnboardingAssignmentStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KnowledgeController extends Controller
{
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
}
