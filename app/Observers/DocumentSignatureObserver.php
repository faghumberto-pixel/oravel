<?php

namespace App\Observers;

use App\Mail\SignatureLinkMail;
use App\Models\DocumentSignature;
use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Services\TenantTimeline;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Facades\LogActivity;

class DocumentSignatureObserver
{
    /**
     * Handle the DocumentSignature "created" event.
     */
    public function created(DocumentSignature $signature): void
    {
        try {
            LogActivity::useLog('document-signatures')
                ->performedOn($signature)
                ->withProperties([
                    'signer_name' => $signature->signer_name,
                    'signer_email' => $signature->signer_email,
                ])
                ->log('Assinatura solicitada');
        } catch (\Throwable $e) {
            \Log::warning('LogActivity não disponível para DocumentSignature', ['error' => $e->getMessage()]);
        }

        if ($signature->signable_type === Tenant::class) {
            TenantTimeline::record(
                $signature->signable_id,
                TenantEvent::CONTRATO_LINK,
                'Link do contrato de assinatura gerado',
                'Válido até '.$signature->expires_at?->format('d/m/Y H:i').'. Signatário: '.($signature->signer_name ?: '—').'.',
                dedupeKey: 'contrato-link:'.$signature->id,
            );
        }

        // Dispara notificações por e-mail e/ou WhatsApp
        $this->notifySignatories($signature);
    }

    /**
     * Handle the DocumentSignature "updated" event.
     */
    public function updated(DocumentSignature $signature): void
    {
        try {
            // Log apenas mudanças relevantes
            if ($signature->wasChanged('status')) {
                LogActivity::useLog('document-signatures')
                    ->performedOn($signature)
                    ->withProperties([
                        'old_status' => $signature->getOriginal('status'),
                        'new_status' => $signature->status,
                    ])
                    ->log("Status alterado para {$signature->status}");
            }

            if ($signature->wasChanged('signed_at')) {
                LogActivity::useLog('document-signatures')
                    ->performedOn($signature)
                    ->withProperties([
                        'signed_at' => $signature->signed_at,
                    ])
                    ->log('Documento assinado');
            }
        } catch (\Throwable $e) {
            \Log::warning('LogActivity não disponível para DocumentSignature', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle the DocumentSignature "deleted" event.
     */
    public function deleted(DocumentSignature $signature): void
    {
        try {
            LogActivity::useLog('document-signatures')
                ->performedOn($signature)
                ->log('Assinatura deletada');
        } catch (\Throwable $e) {
            \Log::warning('LogActivity não disponível para DocumentSignature', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle the DocumentSignature "restored" event.
     */
    public function restored(DocumentSignature $signature): void
    {
        try {
            LogActivity::useLog('document-signatures')
                ->performedOn($signature)
                ->log('Assinatura restaurada');
        } catch (\Throwable $e) {
            \Log::warning('LogActivity não disponível para DocumentSignature', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle the DocumentSignature "force deleted" event.
     */
    public function forceDeleted(DocumentSignature $signature): void
    {
        try {
            LogActivity::useLog('document-signatures')
                ->performedOn($signature)
                ->log('Assinatura permanentemente deletada');
        } catch (\Throwable $e) {
            \Log::warning('LogActivity não disponível para DocumentSignature', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Envia o link de assinatura ao signatário por e-mail, na hora (sendNow: o e-mail do link não pode depender de worker de fila).
     *
     * Antes isto usava um "notificável anônimo" com rota de e-mail vazia: o e-mail nunca saía e, com fila, o job nem serializava
     * ("Serialization of class@anonymous is not allowed", visto em PROD em 18/10/2026). O aviso por WhatsApp dependia do mesmo
     * notificável (canal de banco sem usuário) e também nunca funcionou, por isso foi retirado daqui.
     */
    private function notifySignatories(DocumentSignature $signature): void
    {
        if (! $signature->signer_email) {
            return;
        }

        try {
            Mail::to($signature->signer_email)->sendNow(new SignatureLinkMail($signature));
        } catch (\Throwable $e) {
            \Log::error('Erro ao enviar o link de assinatura por e-mail', [
                'signature_id' => $signature->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
