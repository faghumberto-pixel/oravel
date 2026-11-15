<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login do app instalavel do Assistente de Prospeccao (/assistente) -- mesmo
 * padrao do chat standalone (/chat): sessao longa ("remember"), sem passar
 * pelo painel. Restrito a super admin (mesma regra do chat da Central).
 */
class AssistenteSessionController extends Controller
{
    public function create(): View
    {
        return view('assistente.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        // Teclado do celular costuma por a 1a letra em maiuscula e espaco no fim: e-mail e' comparado exato.
        $request->merge(['remember' => true, 'email' => Str::lower(trim((string) $request->input('email')))]);

        $request->authenticate();

        $user = Auth::user();

        // O destino vem de QUEM entrou, nao de qual tela de login foi usada (antes, entrar pelo login
        // de Prospeccao com conta de cliente dava "Acesso restrito"; o "Sair" cai nesse login):
        // super admin -> Prospeccao (so ele); usuario de cliente -> Suprimentos.
        $destino = match (true) {
            (bool) $user?->isSuperAdmin() => 'assistente.index',
            filled($user?->tenant_id) => 'assistente.suprimentos',
            default => null,
        };

        if ($destino === null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages(['email' => 'Acesso restrito.']);
        }

        $request->session()->regenerate();

        return redirect()->route($destino);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Volta ao login da mesma area (cor/icone/manifesto certos).
        return redirect()->route('assistente.login', $request->input('area') === 'suprimentos' ? ['area' => 'suprimentos'] : []);
    }
}
