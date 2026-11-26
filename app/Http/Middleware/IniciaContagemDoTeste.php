<?php

namespace App\Http\Middleware;

use App\Domain\Intelligence\Services\DadosDeExemplo;
use App\Domain\Intelligence\Trial\Services\TrialCiclo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Primeiro acesso autenticado de quem tem a conta do teste grátis do Intelligence (a entrada é sempre por e-mail e senha):
 *  - inicia a contagem dos dias, se o teste conta "desde o primeiro acesso" (intelligence_trial.inicio = cadastro; idempotente, nunca reinicia);
 *  - mostra os dados de exemplo na 1ª abertura (e apaga o que sobrou depois do prazo).
 * Roda uma vez por sessão; quem não tem conta de teste (ERP, outros pacotes) não paga consulta nenhuma depois da 1ª.
 */
class IniciaContagemDoTeste
{
    private const CHAVE_SESSAO = 'intelligence_contagem_checada';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && filled($user->tenant_id) && $request->hasSession() && ! $request->session()->get(self::CHAVE_SESSAO)) {
            if ($trial = TrialCiclo::doTenant($user->tenant_id)) {
                if (config('intelligence_trial.inicio') === 'cadastro') {
                    app(TrialCiclo::class)->iniciarContagem($trial);
                }
                app(DadosDeExemplo::class)->aoAbrir($trial);
            }
            $request->session()->put(self::CHAVE_SESSAO, true);
        }

        return $next($request);
    }
}
