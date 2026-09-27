<?php

namespace App\View\Composers;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\DemandTask;
use Illuminate\View\View;

class TaskTrayComposer
{
    public function compose(View $view): void
    {
        $user = request()->user();
        if (! $user || $user->role !== UserRole::Professional) {
            $view->with(['taskTrayTasks' => collect(), 'taskTrayActiveEntry' => null, 'taskTrayHasMore' => false, 'taskTrayElapsedSeconds' => 0]);

            return;
        }

        $activeEntry = $user->activeTimeEntry()->with('task.demand')->first();
        $taskQuery = DemandTask::query()
            ->where('organization_id', $user->organization_id)
            ->where('assigned_to', $user->id)
            ->where(function ($query) use ($activeEntry): void {
                $query->where('status', '!=', TaskStatus::Completed->value);
                if ($activeEntry) {
                    $query->orWhere('id', $activeEntry->task_id);
                }
            })
            ->with(['demand:id,title', 'dependencies:id,status']);
        $taskCount = (clone $taskQuery)->count();
        $tasks = $taskQuery->latest()->limit(21)->get();
        $hasMore = $taskCount > 20;
        $tasks = $tasks->take(20)->sortBy(fn (DemandTask $task): int => $task->id === $activeEntry?->task_id ? 0 : 1)->values();
        $elapsedSeconds = $activeEntry
            ? $activeEntry->task->timeEntries()->whereNotNull('ended_at')->get()->sum(fn ($entry): int => $entry->ended_at->getTimestamp() - $entry->started_at->getTimestamp())
            : 0;

        $view->with([
            'taskTrayTasks' => $tasks,
            'taskTrayActiveEntry' => $activeEntry,
            'taskTrayHasMore' => $hasMore,
            'taskTrayElapsedSeconds' => $elapsedSeconds,
        ]);
    }
}
