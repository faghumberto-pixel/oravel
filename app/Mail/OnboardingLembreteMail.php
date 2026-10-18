<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Lembrete curto do onboarding (boas-vindas, passo 1 parado, passo 3 parado, depois de 7 dias). Texto vem pronto de OnboardingLembretes. */
class OnboardingLembreteMail extends Mailable
{
    /** @param  list<string>  $paragrafos */
    public function __construct(
        public string $assunto,
        public string $saudacao,
        public array $paragrafos,
        public string $botao,
        public string $link,
        public ?string $linkAjuda = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->assunto);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.onboarding-lembrete');
    }
}
