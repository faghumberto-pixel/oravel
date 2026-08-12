<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Substitui o middleware 'auth' padrão só dentro do grupo /chat -- sem
 * isso, um visitante não autenticado seria mandado para route('login')
 * (tela do painel Filament), quebrando o isolamento do chat standalone.
 * Escopo restrito de propósito: não altera o comportamento do middleware
 * 'auth' global usado pelo resto da aplicação.
 *
 * Requisições que esperam JSON (ex: o endpoint de sincronização offline,
 * chamado via fetch() puro, não navegação de página) recebem 401 em vez
 * de redirect -- um redirect não faz sentido pra um fetch() em background.
 */
class RedirectGuestToChatLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            if ($request->expectsJson()) {
                abort(401, 'Não autenticado.');
            }

            return redirect()->guest(route('chat.login'));
        }

        return $next($request);
    }
}
