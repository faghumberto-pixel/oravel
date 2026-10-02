<?php

namespace App\Http\Middleware;

use App\Models\Course;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Porta de entrada das telas /academia (fora do painel Filament, entao nao herda o middleware
 * dele): exige login, respeita o bloqueio por inadimplencia e a mesma regra do menu antigo
 * (modulo Academia no contrato + permissao via Perfis de Acesso).
 */
class EnsureAcademyAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest('/admin/login');
        }

        if ($user->tenant?->isAccessBlockedForNonPayment()) {
            return redirect()->route('admin.conta-bloqueada');
        }

        abort_unless(Gate::forUser($user)->allows('viewAny', Course::class), 403);

        return $next($request);
    }
}
