<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Exibe a tela de login.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Processa a solicitação de autenticação.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // 1. Autentica as credenciais
        $request->authenticate();

        // 2. Regenera a sessão para prevenir ataques de fixação de sessão
        $request->session()->regenerate();

        // 3. Define o tenant na sessão baseando-se no usuário logado
        // Isso é o que permite o Global Scope funcionar no seu BaseModel
        session(['tenant_id' => Auth::user()->tenant_id]);
        
        // 4. FORÇA A GRAVAÇÃO: Isso garante que o arquivo da sessão 
        // seja escrito em disco antes do redirecionamento
        $request->session()->save();

        // 5. Redireciona para o dashboard
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Encerra a sessão autenticada.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Limpa a referência do tenant ao deslogar
        $request->session()->forget('tenant_id');

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}