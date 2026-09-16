<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SignatureReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Tenant $tenant)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Oravel — Lembrete: Assinatura de Contrato de Serviço',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.signature-reminder',
            with: [
                'tenant' => $this->tenant,
                'daysLeft' => $this->tenant->signature_required_by ? now()->diffInDays($this->tenant->signature_required_by) : 0,
            ],
        );
    }
}
