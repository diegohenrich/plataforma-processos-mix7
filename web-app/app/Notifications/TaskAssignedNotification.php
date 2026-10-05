<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $demandId,
        public string $demandTitle,
        public int $taskId,
        public string $taskTitle,
        public string $assignedByName,
        public bool $reassigned = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = $this->reassigned
            ? $this->assignedByName.' transferiu a tarefa “'.$this->taskTitle.'” para você na demanda “'.$this->demandTitle.'”.'
            : $this->assignedByName.' atribuiu a tarefa “'.$this->taskTitle.'” a você na demanda “'.$this->demandTitle.'”.';

        return [
            'type' => $this->reassigned ? 'task_reassigned' : 'task_assigned',
            'demand_id' => $this->demandId,
            'demand_title' => $this->demandTitle,
            'task_id' => $this->taskId,
            'task_title' => $this->taskTitle,
            'assigned_by' => $this->assignedByName,
            'message' => $message,
        ];
    }
}
