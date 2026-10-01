<?php

namespace App\Listeners;

use App\Events\DocumentSigned;
use App\Filament\Central\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Avisa a Central (super admins, sino de notificações do painel /central)
 * quando um documento é assinado -- principalmente o Contrato de Assinatura
 * de um cliente novo, que o operador fica esperando antes de seguir pro
 * pagamento. Método chamado notify() (não handle()) de propósito: o projeto
 * registra listeners explicitamente em AppServiceProvider, e um método
 * "handle" também seria registrado de novo pela descoberta automática do
 * Laravel, avisando em duplicidade.
 */
class NotifyCentralOfSignedDocument
{
    public function notify(DocumentSigned $event): void
    {
        try {
            $signature = $event->signature->refresh();
            $signable = $signature->signable;

            $isTenantContract = $signable instanceof Tenant;
            $subject = $isTenantContract
                ? $signable->name
                : class_basename((string) $signature->signable_type);

            $notification = Notification::make()
                ->title(($isTenantContract ? 'Contrato assinado: ' : 'Documento assinado: ').$subject)
                ->body(sprintf(
                    '%s assinou em %s%s.',
                    $signature->signer_name ?: 'O signatário',
                    ($signature->signed_at ?? now())->format('d/m/Y H:i'),
                    $isTenantContract ? ' — o cliente segue para o pagamento da mensalidade' : ''
                ))
                ->icon('heroicon-o-check-badge')
                ->iconColor('success')
                ->success();

            if ($isTenantContract) {
                $notification->actions([
                    Action::make('open')
                        ->label('Abrir empresa')
                        ->url(TenantResource::getUrl('edit', ['record' => $signable], panel: 'central')),
                ]);
            }

            $recipients = User::withoutGlobalScopes()
                ->whereIn(DB::raw('lower(email)'), array_map('strtolower', config('oravel.super_admins', [])))
                ->get();

            foreach ($recipients as $recipient) {
                // sendNow: não depende de worker de fila (mesmo cuidado de UserResource).
                NotificationFacade::sendNow($recipient, $notification->toDatabase());
            }
        } catch (\Throwable $e) {
            // Nunca derruba a assinatura por causa do aviso.
            Log::warning('NotifyCentralOfSignedDocument: falha ao avisar a central.', ['error' => $e->getMessage()]);
        }
    }
}
