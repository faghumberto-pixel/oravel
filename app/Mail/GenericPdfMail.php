<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail generico com corpo de texto + anexo PDF opcional -- base
 * reutilizavel pra qualquer fluxo que precise mandar um PDF por e-mail
 * (laudo de avaria, orçamento, dossiê), sem precisar de uma classe Mailable
 * nova pra cada caso. Especializar (ex: App\Mail\QuoteMail) so' faz sentido
 * quando o fluxo especifico precisar de logica alem de "corpo + anexo".
 */
class GenericPdfMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $greeting,
        public string $bodyText,
        public ?string $pdfContent = null,
        public ?string $pdfFilename = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.generic-pdf',
            with: [
                'greeting' => $this->greeting,
                'bodyText' => $this->bodyText,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->pdfContent) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->pdfFilename ?? 'documento.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
