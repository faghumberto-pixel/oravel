<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckTempPassword
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        // Se o usuário existir e tiver um valor em temp_password, ele é forçado a trocar
        if ($user && $user->temp_password !== null) {
            // Permite o acesso apenas se ele já estiver na tela de troca de senha
            if (!$request->is('admin/trocar-senha')) {
                return redirect()->route('admin.trocar-senha');
            }
        }

        return $next($request);
    }
}