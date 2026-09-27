<?php

namespace App\Notifications;

use App\Models\Demand;
use App\Models\DemandReviewLink;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientReviewLinkNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Demand $demand,
        public DemandReviewLink $reviewLink,
        public string $reviewUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A Mix7 enviou uma versão para sua aprovação')
            ->greeting('Olá!')
            ->line('A equipe Mix7 compartilhou a versão '.$this->reviewLink->version.' de “'.$this->demand->title.'” para sua revisão.')
            ->action('Revisar material', $this->reviewUrl)
            ->line('Você pode comentar, anotar, aprovar ou pedir ajustes sem criar uma conta.')
            ->line('Este link fica disponível até '.$this->reviewLink->expires_at->format('d/m/Y H:i').'.')
            ->salutation('Equipe Mix7');
    }
}
