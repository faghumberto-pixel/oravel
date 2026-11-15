<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Support\Assistente\Sessao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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

        // Confere e-mail e senha SEM autenticar ainda: autenticar e depois desfazer (logout) troca o token
        // de "lembrar de mim" do usuario e derrubaria o login dele no outro app.
        $request->ensureIsNotRateLimited();

        $provider = Auth::getProvider();
        $credenciais = $request->only('email', 'password');
        $user = $provider->retrieveByCredentials($credenciais);

        if (! $user || ! $provider->validateCredentials($user, $credenciais)) {
            RateLimiter::hit($request->throttleKey());

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        $area = Sessao::area($request) === 'suprimentos' ? 'suprimentos' : 'prospects';

        // Cada app tem sessao e usuario proprios (ver Sessao). Prospeccao: so super admin. Suprimentos:
        // so usuario de cliente (o super admin fica na Central / Oravel Mobile).
        $erro = match (true) {
            $area === 'suprimentos' && $user->isSuperAdmin() => 'Esta é uma conta de administrador. No Suprimentos entre com um usuário de cliente (o administrador usa o app Oravel Mobile).',
            $area === 'suprimentos' && blank($user->tenant_id) => 'Esta conta não é de nenhum cliente.',
            $area === 'prospects' && ! $user->isSuperAdmin() => 'Esta conta é de cliente. Para Suprimentos use o app Oravel Suprimentos.',
            default => null,
        };

        if ($erro !== null) {
            throw ValidationException::withMessages(['email' => $erro]);
        }

        RateLimiter::clear($request->throttleKey());
        Auth::login($user, true); // "lembrar de mim" sempre ligado: so sai por acao manual

        $request->session()->regenerate();

        return redirect()->route($area === 'suprimentos' ? 'assistente.suprimentos' : 'assistente.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Volta ao login da mesma area (cor/icone/manifesto certos).
        return redirect()->route('assistente.login', $request->input('area') === 'suprimentos' ? ['area' => 'suprimentos'] : []);
    }
}
