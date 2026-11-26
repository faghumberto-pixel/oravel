<?php

namespace App\Domain\Intelligence\Trial\Mail;

use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Support\TelefoneBr;

/** Aviso ao dono a cada novo cadastro de teste. */
class NovoCadastroTrialMail extends TrialMail
{
    public function __construct(IntelligenceTrial $trial)
    {
        parent::__construct(
            'Novo teste grátis do Intelligence: '.$trial->empresa,
            ['Nome: '.$trial->nome, 'Empresa: '.$trial->empresa, 'WhatsApp: '.TelefoneBr::formatar($trial->whatsapp), 'Origem: Intelligence – teste grátis', ...($trial->objetivo ? ['Quer descobrir: '.$trial->objetivoRotulo()] : [])],
        );
    }
}
