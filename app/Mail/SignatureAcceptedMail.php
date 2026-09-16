<?php

namespace App\Mail;

use App\Models\Signature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SignatureAcceptedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Signature $signature)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Oravel — Assinatura Eletrônica Confirmada',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.signature-accepted',
            with: [
                'signature' => $this->signature,
            ],
        );
    }
}
