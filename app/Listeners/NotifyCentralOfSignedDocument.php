<?php

namespace App\Listeners;

use App\Events\DocumentSigned;
use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Models\User;
use App\Services\TenantTimeline;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Quando um documento é assinado: se é o Contrato de Assinatura de um cliente,
 * registra na linha do tempo do cliente e avisa a Central (sino dos super
 * admins); outros documentos só avisam a Central. Método chamado notify() (não
 * handle()) de propósito: o projeto registra listeners explicitamente em
 * AppServiceProvider, e um "handle" também seria registrado pela descoberta
 * automática do Laravel, duplicando o aviso.
 */
class NotifyCentralOfSignedDocument
{
    public function notify(DocumentSigned $event): void
    {
        try {
            $signature = $event->signature->refresh();
            $signable = $signature->signable;
            $when = ($signature->signed_at ?? now());

            if ($signable instanceof Tenant) {
                TenantTimeline::record(
                    $signable,
                    TenantEvent::CONTRATO_ASSINADO,
                    'Contrato assinado: '.$signable->name,
                    sprintf(
                        '%s assinou em %s (IP %s). O cliente segue para o pagamento da mensalidade.',
                        $signature->signer_name ?: 'O signatário',
                        $when->format('d/m/Y H:i'),
                        $signature->ip_address ?: '—'
                    ),
                    properties: ['signature_id' => $signature->id, 'document_hash' => $signature->document_hash],
                    dedupeKey: 'contrato-assinado:'.$signature->id,
                    notify: true,
                    level: 'success',
                    at: $when,
                );

                return;
            }

            $notification = Notification::make()
                ->title('Documento assinado: '.class_basename((string) $signature->signable_type))
                ->body(sprintf('%s assinou em %s.', $signature->signer_name ?: 'O signatário', $when->format('d/m/Y H:i')))
                ->icon('heroicon-o-check-badge')
                ->iconColor('success')
                ->success();

            $recipients = User::withoutGlobalScopes()
                ->whereIn(DB::raw('lower(email)'), array_map('strtolower', config('oravel.super_admins', [])))
                ->get();

            foreach ($recipients as $recipient) {
                NotificationFacade::sendNow($recipient, $notification->toDatabase());
            }
        } catch (\Throwable $e) {
            // Nunca derruba a assinatura por causa do aviso/histórico.
            Log::warning('NotifyCentralOfSignedDocument: falha.', ['error' => $e->getMessage()]);
        }
    }
}
