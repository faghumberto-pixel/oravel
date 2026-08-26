<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Disparada pela Action "Conceder acesso ao portal" em ClientResource.
 * Só 'mail' -- Client não é User do painel admin, não tem sino.
 */
class ClientPortalAccessGranted extends Notification
{
    use Queueable;

    public function __construct(
        private string $temporaryPassword,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Seu acesso ao Portal do Cliente Oravel')
            ->line("Olá, {$notifiable->name}!")
            ->line('Você agora tem acesso ao Portal do Cliente, onde pode acompanhar seus contratos, equipamentos, chamados de manutenção e financeiro.')
            ->line("E-mail de acesso: {$notifiable->email}")
            ->line("Senha temporária: {$this->temporaryPassword}")
            ->action('Acessar o Portal', url('/cliente/login'))
            ->line('Por segurança, recomendamos trocar essa senha assim que possível junto ao seu contato na locadora.');
    }
}
