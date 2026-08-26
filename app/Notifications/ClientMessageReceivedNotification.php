<?php

namespace App\Notifications;

use App\Models\ClientMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ClientMessageReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private ClientMessage $message,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $cliente = $this->message->client?->name ?? 'Cliente';
        $preview = $this->message->body ? Str::limit($this->message->body, 200) : '(anexo enviado)';

        return (new MailMessage)
            ->subject("Nova mensagem de {$cliente}")
            ->line($preview)
            ->action('Ver e responder', url('/admin'));
    }
}
