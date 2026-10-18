<?php

namespace App\Mail;

use App\Models\NotaFiscal;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** E-mail com os links da NFS-e para o cliente. Sai pela caixa de e-mail da empresa quando ela cadastrou uma (TenantAwareTransport). */
class NotaFiscalMail extends Mailable
{
    public function __construct(public NotaFiscal $nota, public string $empresa) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nota fiscal de serviço nº '.$this->nota->numero.' — '.$this->empresa);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.nota-fiscal');
    }
}
