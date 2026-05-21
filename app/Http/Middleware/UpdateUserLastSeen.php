<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class UpdateUserLastSeen
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            
            // 🔥 BLINDAGEM DE EVENTOS: Isola completamente o salvamento do usuário,
            // evitando que pacotes de terceiros (como Spatie) tentem injetar queries de verificação
            // de papéis/permissões no meio do fluxo do Livewire.
            $user::withoutEvents(function () use ($user) {
                $user->timestamps = false; // Evita disparar observers ou alterar o updated_at geral
                $user->last_seen_at = Carbon::now('America/Sao_Paulo');
                $user->save();
            });
        }

        return $next($request);
    }
}