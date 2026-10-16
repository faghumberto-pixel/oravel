<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Porta de entrada da API do CRM (/api/crm/v1): depois do auth:sanctum, exige
 * a habilidade do token, usuário aprovado, empresa existente e não bloqueada por
 * inadimplência e o módulo de CRM liberado no contrato. Todas as respostas de
 * erro seguem {erro: {codigo, mensagem}}.
 */
class CrmApiAccess
{
    public function handle(Request $request, Closure $next, string $habilidade = 'crm:read'): Response
    {
        $request->headers->set('Accept', 'application/json');

        $user = $request->user();

        if (! $user || ! $user->tokenCan($habilidade)) {
            return $this->erro('sem_habilidade', "Este token não tem a habilidade \"{$habilidade}\".", 403);
        }

        if (! $user->is_approved) {
            return $this->erro('usuario_nao_aprovado', 'Usuário ainda não aprovado.', 403);
        }

        $tenant = $user->tenant;

        if (! $tenant) {
            return $this->erro('sem_empresa', 'Usuário sem empresa vinculada.', 403);
        }

        if ($tenant->isAccessBlockedForNonPayment()) {
            return $this->erro('conta_bloqueada', 'Conta bloqueada por pendência financeira.', 403);
        }

        if (! $tenant->hasFeature('tabela_crm_leads')) {
            return $this->erro('modulo_desligado', 'O módulo de CRM não está liberado no contrato.', 403);
        }

        return $next($request);
    }

    private function erro(string $codigo, string $mensagem, int $status): Response
    {
        return response()->json(['erro' => ['codigo' => $codigo, 'mensagem' => $mensagem]], $status);
    }
}
