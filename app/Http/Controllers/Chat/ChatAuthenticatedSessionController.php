<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Cópia fina de App\Http\Controllers\Auth\AuthenticatedSessionController,
 * dedicada à experiência standalone do chat (/chat) -- não reaproveita o
 * controller do painel porque ele redireciona para route('dashboard') e
 * redirect('/') (painel), o que quebraria o isolamento do PWA de chat.
 *
 * Sempre autentica com "remember" ligado -- pedido explícito do usuário:
 * o funcionário/técnico só deve deslogar por ação manual ou reinício do
 * aparelho, nunca por expiração de sessão (cookie remember_token dura
 * ~5 anos, independente do SESSION_LIFETIME).
 */
class ChatAuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('chat.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->merge(['remember' => true]);

        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('chat.index', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('chat.login');
    }
}
