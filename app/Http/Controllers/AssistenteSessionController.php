<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $request->merge(['remember' => true]);

        $request->authenticate();

        if (! Auth::user()?->isSuperAdmin()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages(['email' => 'Acesso restrito.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('assistente.index', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('assistente.login');
    }
}
