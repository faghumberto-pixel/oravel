<?php

namespace App\Http\Middleware;

use App\Filament\Resources\CourseResource;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Porta de entrada das telas /academia (fora do painel Filament, entao nao herda o middleware
 * dele). Quem tem cadastro no app entra: exige login (e cadastro aprovado), respeita o bloqueio
 * por inadimplencia e o modulo Academia ligado no contrato. NAO exige permissao especifica em
 * Perfis de Acesso -- a Academia e' pra toda a equipe.
 */
class EnsureAcademyAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest('/admin/login');
        }

        abort_unless(CourseResource::canViewAny(), 403);

        if (! $user->isSuperAdmin() && $user->tenant?->isAccessBlockedForNonPayment()) {
            return redirect()->route('admin.conta-bloqueada');
        }

        return $next($request);
    }
}
