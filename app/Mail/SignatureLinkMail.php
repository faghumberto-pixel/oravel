<?php

namespace App\Mail;

use App\Models\DocumentSignature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SignatureLinkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public DocumentSignature $signature
    ) {
        $this->queue = 'default';
        $this->delay = now()->addSeconds(5);
    }

    public function envelope(): Envelope
    {
        $documentType = match ($this->signature->signable_type) {
            'App\\Models\\Contract' => 'Contrato de Locação',
            'App\\Models\\MaintenanceOrder' => 'Ordem de Serviço',
            default => 'Documento',
        };

        return new Envelope(
            subject: "Assinatura Eletrônica - {$documentType}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signature-link',
            with: [
                'signature' => $this->signature,
                'link' => route('signature.sign', ['token' => $this->signature->token]),
                'expiresAt' => $this->signature->expires_at->format('d/m/Y'),
                'documentType' => match ($this->signature->signable_type) {
                    'App\\Models\\Contract' => 'Contrato de Locação',
                    'App\\Models\\MaintenanceOrder' => 'Ordem de Serviço',
                    default => 'Documento',
                },
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
