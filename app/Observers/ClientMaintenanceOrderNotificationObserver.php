<?php

namespace App\Observers;

use App\Models\MaintenanceOrder;
use App\Notifications\ClientRequestStatusUpdatedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Observer novo e isolado, dedicado só a notificar o Client por e-mail
 * quando a OS dele muda de status -- deliberadamente separado do
 * App\Observers\MaintenanceOrderObserver legado (existe no disco mas não
 * está registrado em AppServiceProvider; seu conteúdo duplica lógica de
 * checklist já coberta por MaintenanceOrderChecklistSnapshotObserver, não
 * deve ser ativado como efeito colateral desta feature).
 */
class ClientMaintenanceOrderNotificationObserver
{
    public function updated(MaintenanceOrder $order): void
    {
        if (! $order->wasChanged('status') || ! $order->client_id) {
            return;
        }

        if (! in_array($order->status, ['Concluída', 'Completado', 'Cancelada'], true)) {
            return;
        }

        $client = $order->client;
        if (! $client?->portal_access_enabled_at) {
            return;
        }

        Notification::send($client, new ClientRequestStatusUpdatedNotification(
            'Chamado de Manutenção — '.$order->os_number,
            $order->status,
            '/cliente/minhas-os',
        ));
    }
}
