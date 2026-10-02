<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\DemandTask;
use App\Models\TaskTimeEntry;
use App\Models\User;
use App\Services\TaskTimerHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TeamActivityController extends Controller
{
    public function now(Request $request): JsonResponse
    {
        $this->authorize('viewActivity', User::class);
        app(TaskTimerHeartbeat::class)->closeAllStale();
        $viewer = $request->user();
        $personal = $viewer->role === UserRole::Professional;
        $professionals = User::query()
            ->where('organization_id', $viewer->organization_id)
            ->where('role', UserRole::Professional->value)
            ->when($personal, fn ($query) => $query->whereKey($viewer->id))
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
        $ids = $professionals->modelKeys();
        $tasks = DemandTask::query()
            ->where('organization_id', $viewer->organization_id)
            ->whereIn('assigned_to', $ids)
            ->where('status', '!=', TaskStatus::Completed->value)
            ->with('demand:id,title')
            ->orderBy('assigned_to')->orderBy('title')
            ->get(['id', 'assigned_to', 'demand_id', 'title', 'status', 'estimate_minutes', 'planned_start_on', 'planned_due_on']);
        $timers = TaskTimeEntry::query()
            ->where('organization_id', $viewer->organization_id)
            ->whereIn('user_id', $ids)
            ->whereNull('ended_at')
            ->get(['id', 'user_id', 'task_id', 'started_at'])
            ->keyBy('task_id');

        $data = $professionals->map(function (User $professional) use ($tasks, $timers): array {
            $commitments = $tasks->where('assigned_to', $professional->id)->map(function (DemandTask $task) use ($timers): array {
                $timer = $timers->get($task->id);

                return [
                    'task_id' => $task->id,
                    'task' => $task->title,
                    'demand' => $task->demand?->title,
                    'status' => $task->status->value,
                    'status_label' => $task->status->label(),
                    'estimate_minutes' => $task->estimate_minutes,
                    'planned_start_on' => $task->planned_start_on?->toDateString(),
                    'planned_due_on' => $task->planned_due_on?->toDateString(),
                    'timer_running' => $timer !== null,
                    'timer_started_at' => $timer?->started_at?->toISOString(),
                ];
            })->values();

            return [
                'professional_id' => $professional->id,
                'professional' => $professional->name,
                'active' => $professional->is_active,
                'commitments' => $commitments,
            ];
        })->values();

        return response()->json(['data' => ['refreshed_at' => now()->toISOString(), 'professionals' => $data]]);
    }

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewActivity', User::class);
        app(TaskTimerHeartbeat::class)->closeAllStale();
        $viewer = $request->user();
        $personal = $viewer->role === UserRole::Professional;
        $periodStart = CarbonImmutable::now()->subDays(30);
        $now = CarbonImmutable::now();

        $professionals = User::query()
            ->where('organization_id', $viewer->organization_id)
            ->where('role', UserRole::Professional->value)
            ->when($personal, fn ($query) => $query->whereKey($viewer->id))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
        $professionalIds = $professionals->modelKeys();

        $taskGroups = DemandTask::query()
            ->selectRaw('assigned_to, status, COUNT(*) as task_count, SUM(CASE WHEN status != ? THEN COALESCE(estimate_minutes, 0) ELSE 0 END) as open_estimate_minutes', [TaskStatus::Completed->value])
            ->where('organization_id', $viewer->organization_id)
            ->whereIn('assigned_to', $professionalIds)
            ->groupBy('assigned_to', 'status')
            ->get()
            ->groupBy('assigned_to');

        $completedCounts = DemandTask::query()
            ->selectRaw('assigned_to, COUNT(*) as task_count')
            ->where('organization_id', $viewer->organization_id)
            ->whereIn('assigned_to', $professionalIds)
            ->where('status', TaskStatus::Completed->value)
            ->where('completed_at', '>=', $periodStart)
            ->groupBy('assigned_to')
            ->pluck('task_count', 'assigned_to');

        $weekStarts = [];
        $firstWeek = $periodStart->startOfWeek();
        for ($week = $firstWeek; $week->lessThanOrEqualTo($now); $week = $week->addWeek()) {
            $weekStarts[$week->toDateString()] = $week;
        }
        $weekly = [];
        foreach ($professionalIds as $professionalId) {
            foreach ($weekStarts as $weekKey => $weekStart) {
                $weekly[$professionalId][$weekKey] = ['completed' => 0, 'recorded_seconds' => 0];
            }
        }

        DemandTask::query()
            ->where('organization_id', $viewer->organization_id)
            ->whereIn('assigned_to', $professionalIds)
            ->where('status', TaskStatus::Completed->value)
            ->where('completed_at', '>=', $periodStart)
            ->orderBy('id')
            ->cursor()
            ->each(function (DemandTask $task) use (&$weekly): void {
                if ($task->completed_at) {
                    $weekKey = $task->completed_at->startOfWeek()->toDateString();
                    if (isset($weekly[$task->assigned_to][$weekKey])) {
                        $weekly[$task->assigned_to][$weekKey]['completed']++;
                    }
                }
            });

        $recordedSeconds = array_fill_keys($professionalIds, 0);
        TaskTimeEntry::query()
            ->where('organization_id', $viewer->organization_id)
            ->whereIn('user_id', $professionalIds)
            ->where('started_at', '>=', $periodStart)
            ->orderBy('id')
            ->cursor()
            ->each(function (TaskTimeEntry $entry) use (&$recordedSeconds, &$weekly, $now): void {
                $end = $entry->ended_at ?? $now;
                $seconds = $entry->started_at->diffInSeconds($end);
                $recordedSeconds[$entry->user_id] += $seconds;
                $weekKey = $entry->started_at->startOfWeek()->toDateString();
                if (isset($weekly[$entry->user_id][$weekKey])) {
                    $weekly[$entry->user_id][$weekKey]['recorded_seconds'] += $seconds;
                }
            });

        $rows = $professionals->map(function (User $professional) use ($taskGroups, $completedCounts, $recordedSeconds, $weekly, $weekStarts, $periodStart, $now): array {
            $byStatus = $taskGroups->get($professional->id, collect())->keyBy('status');
            $count = fn (TaskStatus $status): int => (int) ($byStatus->get($status->value)->task_count ?? 0);
            $estimateMinutes = (int) $byStatus->sum('open_estimate_minutes');

            return [
                'user' => $professional,
                'is_active' => $professional->is_active,
                'todo' => $count(TaskStatus::Todo),
                'in_progress' => $count(TaskStatus::InProgress),
                'paused' => $count(TaskStatus::Paused),
                'blocked' => $count(TaskStatus::Blocked),
                'open' => $count(TaskStatus::Todo) + $count(TaskStatus::InProgress) + $count(TaskStatus::Paused) + $count(TaskStatus::Blocked),
                'estimate_minutes' => $estimateMinutes,
                'completed_30d' => (int) ($completedCounts[$professional->id] ?? 0),
                'recorded_seconds_30d' => $recordedSeconds[$professional->id] ?? 0,
                'weekly_trend' => collect($weekStarts)->map(function (CarbonImmutable $weekStart) use ($weekly, $professional, $periodStart, $now): array {
                    $weekKey = $weekStart->toDateString();
                    $weekEnd = $weekStart->endOfWeek();

                    return [
                        'week' => $weekStart->format('d/m').'–'.$weekEnd->format('d/m'),
                        'partial' => $weekStart->lessThan($periodStart) || $weekEnd->greaterThan($now),
                        'completed' => $weekly[$professional->id][$weekKey]['completed'] ?? 0,
                        'recorded_seconds' => $weekly[$professional->id][$weekKey]['recorded_seconds'] ?? 0,
                    ];
                })->values()->all(),
            ];
        });

        $myTasks = $personal
            ? DemandTask::query()
                ->where('organization_id', $viewer->organization_id)
                ->where('assigned_to', $viewer->id)
                ->where('status', '!=', TaskStatus::Completed->value)
                ->with('demand:id,title')
                ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'blocked' THEN 1 WHEN 'paused' THEN 2 ELSE 3 END")
                ->orderBy('created_at')
                ->paginate(20)
            : new Collection;
        $activeEntry = $personal
            ? TaskTimeEntry::query()->where('user_id', $viewer->id)->whereNull('ended_at')->with('task:id,title,demand_id')->first()
            : null;

        if ($request->expectsJson()) {
            $professionalRows = $rows->map(fn (array $row): array => [
                'id' => $row['user']->id,
                'name' => $row['user']->name,
                'is_active' => $row['is_active'],
                'tasks' => [
                    'todo' => $row['todo'],
                    'in_progress' => $row['in_progress'],
                    'paused' => $row['paused'],
                    'blocked' => $row['blocked'],
                    'open' => $row['open'],
                ],
                'estimate_minutes' => $row['estimate_minutes'],
                'completed_last_30_days' => $row['completed_30d'],
                'recorded_seconds_last_30_days' => $row['recorded_seconds_30d'],
                'weekly_trend' => $row['weekly_trend'],
            ])->values();
            $personalTasks = $personal
                ? $myTasks->getCollection()->map(fn (DemandTask $task): array => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status->value,
                    'status_label' => $task->status->label(),
                    'estimate_minutes' => $task->estimate_minutes,
                    'demand' => ['id' => $task->demand->id, 'title' => $task->demand->title],
                ])->values()
                : collect();

            return response()->json([
                'data' => [
                    'personal' => $personal,
                    'period_start' => $periodStart->toISOString(),
                    'period_end' => $now->toISOString(),
                    'professionals' => $professionalRows,
                    'my_tasks' => $personalTasks,
                    'active_timer' => $activeEntry ? [
                        'task_id' => $activeEntry->task_id,
                        'task_title' => $activeEntry->task->title,
                        'started_at' => $activeEntry->started_at->toISOString(),
                    ] : null,
                ],
            ]);
        }

        return view('team.activity', [
            'rows' => $rows,
            'personal' => $personal,
            'myTasks' => $myTasks,
            'activeEntry' => $activeEntry,
            'periodStart' => $periodStart,
            'periodEnd' => $now,
        ]);
    }
}
