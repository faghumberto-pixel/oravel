<?php

namespace App\Http\Middleware;

use App\Support\Colaborador\Modulos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ColaboradorAutenticado
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('colab.login');
        }

        // Contrato sem o app (ou desligado depois do login): sai na hora.
        if (! Modulos::liberadoPara($user)) {
            auth()->logout();
            $request->session()->invalidate();

            return redirect()->route('colab.login')->withErrors(['email' => 'O App do Colaborador não está liberado para a sua empresa. Fale com o administrador.']);
        }

        return $next($request);
    }
}
