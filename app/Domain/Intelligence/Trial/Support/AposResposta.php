<?php

namespace App\Domain\Intelligence\Trial\Support;

use Closure;

/**
 * E-mail nunca pode segurar a tela do cliente (SMTP lento ou fora do ar travava o cadastro por ~90s): em requisição web roda
 * depois que a resposta é enviada; em console (comandos e testes) roda na hora.
 */
class AposResposta
{
    public static function executar(Closure $acao): void
    {
        if (app()->runningInConsole()) {
            $acao();

            return;
        }

        dispatch($acao)->afterResponse();
    }
}
