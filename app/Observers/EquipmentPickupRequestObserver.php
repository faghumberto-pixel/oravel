<?php

namespace App\Observers;

use App\Models\EquipmentPickupRequest;
use App\Notifications\ClientRequestStatusUpdatedNotification;
use Illuminate\Support\Facades\Notification;

class EquipmentPickupRequestObserver
{
    public function updated(EquipmentPickupRequest $request): void
    {
        if (! $request->wasChanged('status')) {
            return;
        }

        $client = $request->client;
        if (! $client?->portal_access_enabled_at) {
            return;
        }

        $labels = [
            EquipmentPickupRequest::STATUS_SOLICITADO => 'Solicitado',
            EquipmentPickupRequest::STATUS_AGENDADO => 'Agendado',
            EquipmentPickupRequest::STATUS_CONCLUIDO => 'Concluído',
        ];

        Notification::send($client, new ClientRequestStatusUpdatedNotification(
            'Solicitação de Retirada',
            $labels[$request->status] ?? $request->status,
            '/cliente/solicitar-retirada',
        ));
    }
}
