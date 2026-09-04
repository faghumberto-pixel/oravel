<?php

namespace App\Observers;

use App\Models\DocumentSignature;
use App\Notifications\SignatureLinkMailNotification;
use App\Notifications\SignatureLinkWhatsAppNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Facades\LogActivity;

class DocumentSignatureObserver
{
    /**
     * Handle the DocumentSignature "created" event.
     */
    public function created(DocumentSignature $signature): void
    {
        LogActivity::useLog('document-signatures')
            ->performedOn($signature)
            ->withProperties([
                'signer_name' => $signature->signer_name,
                'signer_email' => $signature->signer_email,
            ])
            ->log('Assinatura solicitada');

        // Dispara notificações por e-mail e/ou WhatsApp
        $this->notifySignatories($signature);
    }

    /**
     * Handle the DocumentSignature "updated" event.
     */
    public function updated(DocumentSignature $signature): void
    {
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
    }

    /**
     * Handle the DocumentSignature "deleted" event.
     */
    public function deleted(DocumentSignature $signature): void
    {
        LogActivity::useLog('document-signatures')
            ->performedOn($signature)
            ->log('Assinatura deletada');
    }

    /**
     * Handle the DocumentSignature "restored" event.
     */
    public function restored(DocumentSignature $signature): void
    {
        LogActivity::useLog('document-signatures')
            ->performedOn($signature)
            ->log('Assinatura restaurada');
    }

    /**
     * Handle the DocumentSignature "force deleted" event.
     */
    public function forceDeleted(DocumentSignature $signature): void
    {
        LogActivity::useLog('document-signatures')
            ->performedOn($signature)
            ->log('Assinatura permanentemente deletada');
    }

    /**
     * Envia notificações de assinatura ao signatário.
     *
     * - E-mail se email fornecido
     * - WhatsApp se telefone fornecido
     */
    private function notifySignatories(DocumentSignature $signature): void
    {
        try {
            // Cria notificável anônimo (não precisa de usuário no sistema)
            $notifiable = new class {
                use \Illuminate\Notifications\Notifiable;

                public function routeNotificationForMail(): string
                {
                    return ''; // Preenchido dinamicamente
                }
            };

            // Envia e-mail
            if ($signature->signer_email) {
                Notification::send($notifiable, new SignatureLinkMailNotification($signature));
            }

            // Envia WhatsApp
            if ($signature->signer_phone) {
                Notification::send($notifiable, new SignatureLinkWhatsAppNotification($signature));
            }

        } catch (\Throwable $e) {
            \Log::error('Erro ao enviar notificações de assinatura', [
                'signature_id' => $signature->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
