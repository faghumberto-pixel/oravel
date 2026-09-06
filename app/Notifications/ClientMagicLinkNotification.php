<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Entrar sem senha" do Portal do Cliente -- disparada por
 * ClientMagicLinkController::send(). Só 'mail', mesmo padrão de
 * ClientPortalAccessGranted (Client não é User do painel admin, sem sino).
 */
class ClientMagicLinkNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $signedUrl,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Seu link de acesso ao Portal do Cliente Oravel')
            ->line("Olá, {$notifiable->name}!")
            ->line('Use o botão abaixo para entrar no Portal do Cliente sem precisar digitar sua senha.')
            ->action('Entrar no Portal', $this->signedUrl)
            ->line('Este link expira em 15 minutos e só pode ser usado uma vez.')
            ->line('Se você não pediu esse acesso, pode ignorar este e-mail com segurança.');
    }
}
