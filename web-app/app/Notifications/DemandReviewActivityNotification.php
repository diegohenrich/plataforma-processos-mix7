<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DemandReviewActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $demandId,
        public string $demandTitle,
        public int $version,
        public string $reviewerName,
        public string $responseType,
        public ?string $comment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $action = match ($this->responseType) {
            'approved' => 'aprovou a versão',
            'changes_requested' => 'pediu ajustes na versão',
            'annotation' => 'anotou um material da versão',
            default => 'comentou sobre a versão',
        };

        return [
            'demand_id' => $this->demandId,
            'demand_title' => $this->demandTitle,
            'version' => $this->version,
            'reviewer_name' => $this->reviewerName,
            'response_type' => $this->responseType,
            'comment' => $this->comment,
            'message' => $this->reviewerName.' '.$action.' '.$this->version.' de “'.$this->demandTitle.'”.',
        ];
    }
}
