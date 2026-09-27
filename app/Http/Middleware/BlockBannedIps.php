<?php

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueio real de IP (pedido do usuário 2026-09-27, botão "Bloquear IP" em
 * Acessos e Visitantes/Central) -- sem isso o botão só marcaria o IP no
 * banco sem nenhum efeito prático. Registrado bem cedo na pilha global
 * (bootstrap/app.php, prepend) pra barrar ANTES de qualquer rota/painel.
 *
 * Cache de 60s (nao por request) porque essa checagem roda em TODA
 * requisicao do site inteiro -- ir no banco toda vez seria desperdicio; um
 * IP recem-bloqueado demora no maximo 1 minuto pra ser barrado de fato,
 * troca aceitavel pela unica tabela pequena (raramente mais que algumas
 * dezenas de linhas).
 */
class BlockBannedIps
{
    public function handle(Request $request, Closure $next): Response
    {
        $blockedIps = Cache::remember(
            'blocked-ips-list',
            60,
            fn () => BlockedIp::pluck('ip_address')->all()
        );

        if (in_array($request->ip(), $blockedIps, true)) {
            abort(403, 'Acesso bloqueado.');
        }

        return $next($request);
    }
}
