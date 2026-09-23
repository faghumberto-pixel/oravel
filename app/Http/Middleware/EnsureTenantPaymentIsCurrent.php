<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueio real por inadimplência (pedido do usuário 2026-09-23) -- ver
 * Tenant::isAccessBlockedForNonPayment() pra regra completa (prazo de
 * tolerância, cancelamento imediato, bypass de super admin). Registrado no
 * authMiddleware do painel 'admin' (AdminPanelProvider), depois de
 * Authenticate::class -- roda em toda navegação autenticada do tenant.
 *
 * Não bloqueia a própria rota de conta bloqueada nem o logout, senão o
 * usuário travado nunca conseguiria nem ver a tela de aviso nem sair.
 */
class EnsureTenantPaymentIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('admin.conta-bloqueada') || $request->routeIs('filament.admin.auth.logout')) {
            return $next($request);
        }

        $tenant = auth()->user()?->tenant;

        if ($tenant?->isAccessBlockedForNonPayment()) {
            return redirect()->route('admin.conta-bloqueada');
        }

        return $next($request);
    }
}
