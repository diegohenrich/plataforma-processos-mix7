<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(public TeamInvitation $invitation, public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Convite para acessar a Plataforma Mix7')
            ->greeting('Olá, '.$this->invitation->name)
            ->line('A direção da agência convidou você para acessar a Plataforma Mix7 como '.$this->invitation->role->label().'.')
            ->action('Ativar minha conta', route('team-invitations.show', $this->token))
            ->line('O link expira em 72 horas e só pode ser usado uma vez. Você criará sua própria senha.')
            ->salutation('Equipe Mix7');
    }
}
