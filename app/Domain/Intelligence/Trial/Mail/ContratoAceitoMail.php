<?php

namespace App\Domain\Intelligence\Trial\Mail;

use App\Domain\Intelligence\Trial\Models\IntelligenceContrato;
use Illuminate\Mail\Mailables\Attachment;

/** Cópia do contrato aceito, em PDF. */
class ContratoAceitoMail extends TrialMail
{
    public function __construct(private IntelligenceContrato $contrato, private string $caminhoPdf)
    {
        parent::__construct('Seu contrato do Oravel Intelligence', [
            'Recebemos o seu aceite. Segue em anexo a cópia do contrato.',
            'A primeira cobrança vence em '.$contrato->billing_starts_on->format('d/m/Y').'. Nada é cobrado antes dessa data.',
        ]);
    }

    public function attachments(): array
    {
        return [Attachment::fromPath($this->caminhoPdf)->as('contrato-oravel-intelligence.pdf')->withMime('application/pdf')];
    }
}
