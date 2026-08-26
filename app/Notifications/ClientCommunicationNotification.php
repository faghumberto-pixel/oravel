<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Comunicado enviado pelo Tenant a um Client específico, vários ou
 * todos (ver GestaoClientes::sendCommunication()) -- assunto e corpo
 * digitados livremente pelo operador, sem template fixo.
 */
class ClientCommunicationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $subject,
        private string $body,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->line($this->body);
    }
}
