<?php

namespace App\Http\Middleware;

use App\Support\Colaborador\Modulos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trava a rota pela liberação que o administrador da empresa deu ao usuário (users.app_access).
 * Uso: colab.modulo:logistica,saida (módulo + função) ou colab.modulo:os|minhas_os (qualquer um dos módulos).
 */
class ColaboradorModulo
{
    public function handle(Request $request, Closure $next, string $modulos, ?string $recurso = null): Response
    {
        $user = $request->user();

        if (! $user || ! Modulos::acessoAlgum($user, explode('|', $modulos), $recurso)) {
            abort(403, 'Seu usuário não tem acesso a esta função do app. Fale com o administrador da sua empresa.');
        }

        return $next($request);
    }
}
