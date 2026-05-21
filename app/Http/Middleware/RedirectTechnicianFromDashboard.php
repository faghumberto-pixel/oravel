<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Filament\Facades\Filament;

class RedirectTechnicianFromDashboard
{
    /**
     * Intercepta o acesso ao Dashboard ou à rota raiz e redireciona
     * técnicos (como o Bruno) diretamente para o Quadro Kanban.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && !$user->isAdmin() && !$user->hasRole('gestor')) {
            $tenantId = Filament::getTenant()?->id;

            if ($tenantId) {
                if ($request->routeIs('filament.admin.pages.dashboard') || $request->path() === 'admin/' . $tenantId) {
                    return redirect('/admin/' . $tenantId . '/maintenance-kanban');
                }
            }
        }

        return $next($request);
    }
}