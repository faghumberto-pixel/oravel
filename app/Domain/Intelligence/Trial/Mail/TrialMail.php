<?php

namespace App\Domain\Intelligence\Trial\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** E-mail simples do Intelligence (título, parágrafos e um botão opcional). */
class TrialMail extends Mailable
{
    /** @param  list<string>  $paragrafos */
    public function __construct(public string $assunto, public array $paragrafos, public ?string $botao = null, public ?string $link = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->assunto);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.intelligence-trial');
    }
}
