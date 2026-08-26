<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reaproveitada pelos 3 fluxos de "resposta a solicitação feita pelo
 * Client no portal" (SolicitacaoLocacao, MaintenanceOrder,
 * EquipmentPickupRequest) -- evita 3 classes quase idênticas.
 */
class ClientRequestStatusUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $requestLabel,
        private string $newStatusLabel,
        private string $portalUrl,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Atualização: {$this->requestLabel}")
            ->line("Sua solicitação (\"{$this->requestLabel}\") teve o status atualizado para: {$this->newStatusLabel}.")
            ->action('Ver no portal', url($this->portalUrl));
    }
}
