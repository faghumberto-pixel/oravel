<?php

namespace App\Notifications;

use App\Models\EquipmentDamage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Disparada quando a avaria já foi confirmada pelo supervisor
 * (STATUS_AGUARDANDO_COMERCIAL) -- não antes, pra não gerar alarme falso
 * enquanto ainda está em triagem interna. Não expõe laudo/causa/valor,
 * só avisa que houve ocorrência.
 */
class ClientEquipmentDamageNotification extends Notification
{
    use Queueable;

    public function __construct(
        private EquipmentDamage $equipmentDamage,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $ativo = $this->equipmentDamage->asset?->name ?? 'equipamento';

        return (new MailMessage)
            ->subject('Ocorrência registrada em equipamento locado')
            ->line("Foi identificada uma avaria no equipamento {$ativo} durante uma ordem de serviço.")
            ->line('A locadora entrará em contato com mais detalhes.')
            ->action('Ver minhas OS', url('/cliente/minhas-os'));
    }
}
