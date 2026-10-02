<?php

namespace App\Services;

use App\Models\DemandTask;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;

class TaskAssignmentNotifier
{
    public function notify(DemandTask $task, User $actor, bool $reassigned = false): void
    {
        $assignee = User::query()
            ->whereKey($task->assigned_to)
            ->where('organization_id', $task->organization_id)
            ->where('is_active', true)
            ->first();

        if (! $assignee || $assignee->is($actor)) {
            return;
        }

        $task->loadMissing('demand:id,organization_id,title');
        if (! $task->demand || $task->demand->organization_id !== $task->organization_id) {
            return;
        }

        $assignee->notify(new TaskAssignedNotification(
            demandId: $task->demand->id,
            demandTitle: $task->demand->title,
            taskId: $task->id,
            taskTitle: $task->title,
            assignedByName: $actor->name,
            reassigned: $reassigned,
        ));
    }
}
