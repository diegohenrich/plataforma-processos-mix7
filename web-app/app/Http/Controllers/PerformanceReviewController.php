<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\DemandTask;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PerformanceReviewController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::AgencyOwner, UserRole::MarketingManager, UserRole::Professional], true), 403);
        $management = in_array($user->role, [UserRole::AgencyOwner, UserRole::MarketingManager], true);
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        if (($filters['from'] ?? null) && ($filters['to'] ?? null) && $filters['to'] < $filters['from']) {
            throw ValidationException::withMessages([
                'to' => 'A data final precisa ser igual ou posterior à data inicial.',
            ]);
        }

        $reviews = PerformanceReview::query()
            ->where('organization_id', $user->organization_id)
            ->when(! $management, fn ($query) => $query->where('professional_id', $user->id))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->with(['task.demand:id,title', 'professional:id,name', 'reviewer:id,name,role', 'responses.user:id,name'])
            ->latest()
            ->paginate(20)
            ->withQueryString();
        $completedTasks = $management
            ? DemandTask::query()->where('organization_id', $user->organization_id)
                ->where('status', TaskStatus::Completed->value)
                ->whereDoesntHave('performanceReviews', fn ($query) => $query->where('reviewer_id', $user->id))
                ->with(['demand:id,title', 'assignee:id,name'])->latest('completed_at')->limit(100)->get()
            : collect();

        return view('team.performance-reviews', compact('reviews', 'completedTasks', 'management', 'filters'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $reviewer = $request->user();
        abort_unless(in_array($reviewer->role, [UserRole::AgencyOwner, UserRole::MarketingManager], true), 403);
        $data = $request->validate([
            'task_id' => ['required', 'integer', Rule::exists('demand_tasks', 'id')->where(fn ($query) => $query
                ->where('organization_id', $reviewer->organization_id)->where('status', TaskStatus::Completed->value))],
            'deadline_score' => ['required', 'integer', 'between:1,5'],
            'quality_score' => ['required', 'integer', 'between:1,5'],
            'deadline_assessment' => ['required', 'string', 'min:10', 'max:5000'],
            'quality_assessment' => ['required', 'string', 'min:10', 'max:5000'],
            'evidence' => ['nullable', 'string', 'max:5000'],
            'external_factors' => ['nullable', 'string', 'max:5000'],
        ]);
        $task = DemandTask::query()->where('organization_id', $reviewer->organization_id)
            ->with('assignee:id,organization_id,role')->findOrFail($data['task_id']);
        abort_unless($task->assignee?->role === UserRole::Professional && $task->assignee->organization_id === $reviewer->organization_id, 422);

        try {
            $review = PerformanceReview::create([
                'organization_id' => $reviewer->organization_id,
                'task_id' => $task->id,
                'professional_id' => $task->assigned_to,
                'reviewer_id' => $reviewer->id,
                'reviewer_role' => $reviewer->role->value,
                'reviewer_weight' => 1,
                'deadline_score' => $data['deadline_score'],
                'quality_score' => $data['quality_score'],
                'deadline_assessment' => $data['deadline_assessment'],
                'quality_assessment' => $data['quality_assessment'],
                'evidence' => $data['evidence'] ?? null,
                'external_factors' => $data['external_factors'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Você já registrou uma avaliação desta tarefa.',
                    'errors' => ['task_id' => ['Você já registrou uma avaliação desta tarefa.']],
                ], 409);
            }

            return back()->withErrors(['task_id' => 'Você já registrou uma avaliação desta tarefa.'])->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Avaliação registrada e disponível para resposta do profissional.',
                'data' => [
                    'id' => $review->id,
                    'task_id' => $review->task_id,
                    'professional_id' => $review->professional_id,
                    'reviewer_id' => $review->reviewer_id,
                    'reviewer_role' => $review->reviewer_role,
                    'reviewer_weight' => $review->reviewer_weight,
                    'deadline_score' => $review->deadline_score,
                    'quality_score' => $review->quality_score,
                    'deadline_assessment' => $review->deadline_assessment,
                    'quality_assessment' => $review->quality_assessment,
                    'evidence' => $review->evidence,
                    'external_factors' => $review->external_factors,
                ],
            ], 201);
        }

        return to_route('performance-reviews.index')->with('success', 'Avaliação registrada e disponível para resposta do profissional.');
    }

    public function respond(Request $request, PerformanceReview $review): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user->is_active && $review->organization_id === $user->organization_id && $review->professional_id === $user->id && $user->role === UserRole::Professional, 403);
        $data = $request->validate(['response' => ['required', 'string', 'min:3', 'max:5000']]);
        $reviewResponse = DB::transaction(fn (): PerformanceReviewResponse => PerformanceReviewResponse::create([
            'performance_review_id' => $review->id,
            'user_id' => $user->id,
            'response' => $data['response'],
        ]));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Sua resposta foi registrada no histórico da avaliação.',
                'data' => [
                    'id' => $reviewResponse->id,
                    'performance_review_id' => $reviewResponse->performance_review_id,
                    'user_id' => $reviewResponse->user_id,
                    'response' => $reviewResponse->response,
                    'created_at' => $reviewResponse->created_at?->toISOString(),
                ],
            ], 201);
        }

        return to_route('performance-reviews.index')->with('success', 'Sua resposta foi registrada no histórico da avaliação.');
    }
}
