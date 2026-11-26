<?php

namespace App\Http\Middleware;

use App\Domain\Intelligence\Trial\Services\TrialCiclo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Teste grátis do Intelligence com contagem "desde o primeiro acesso" (intelligence_trial.inicio = cadastro): QUALQUER acesso ao app
 * de quem tem a conta de teste inicia os dias, mesmo com a sessão já aberta (sem precisar sair e entrar pelo link de novo). Idempotente:
 * nunca reinicia. A checagem roda uma vez por sessão; quem não tem conta de teste (ERP, outros pacotes) não paga consulta nenhuma depois da 1ª.
 */
class IniciaContagemDoTeste
{
    private const CHAVE_SESSAO = 'intelligence_contagem_checada';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && filled($user->tenant_id) && config('intelligence_trial.inicio') === 'cadastro'
            && $request->hasSession() && ! $request->session()->get(self::CHAVE_SESSAO)) {
            if ($trial = TrialCiclo::doTenant($user->tenant_id)) {
                app(TrialCiclo::class)->iniciarContagem($trial);
            }
            $request->session()->put(self::CHAVE_SESSAO, true);
        }

        return $next($request);
    }
}
