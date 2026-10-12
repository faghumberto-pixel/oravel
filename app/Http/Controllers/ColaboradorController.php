<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Employee;
use App\Models\User;
use App\Support\Colaborador\Modulos;
use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ColaboradorController extends BaseController
{
    public function loginForm(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('colab.home') : view('colaborador.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $dados = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $chave = 'colab-login:'.strtolower($dados['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($chave, 5)) {
            throw ValidationException::withMessages(['email' => 'Muitas tentativas. Aguarde um minuto e tente de novo.']);
        }

        if (! Auth::attempt($dados, true)) {
            RateLimiter::hit($chave, 60);
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }

        /** @var User $user */
        $user = Auth::user();

        $motivo = match (true) {
            ! $user->is_approved => 'Sua conta ainda está aguardando aprovação do administrador da sua empresa.',
            ! Modulos::liberadoPara($user) => 'O App do Colaborador não está liberado para a sua empresa. Fale com o administrador.',
            ! $user->canAccessPanel(Filament::getPanel('admin')) => 'Este usuário não tem acesso ao sistema.',
            // O app não pede o código de dois fatores: quem tem 2FA ligado entra pelo painel, para não burlar a proteção.
            method_exists($user, 'hasEnabledTwoFactor') && $user->hasEnabledTwoFactor() => 'Seu usuário usa verificação em duas etapas. Entre pelo painel do sistema.',
            default => null,
        };

        if ($motivo) {
            Auth::logout();
            $request->session()->invalidate();
            throw ValidationException::withMessages(['email' => $motivo]);
        }

        RateLimiter::clear($chave);
        $request->session()->regenerate();

        return redirect()->intended(route('colab.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('colab.login');
    }

    public function home(Request $request): View
    {
        return view('colaborador.home', ['grupos' => Modulos::agrupados($request->user()), 'naoLidas' => app(ColaboradorNotificacoesController::class)->contarNaoLidas($request)]);
    }

    public function escanear(): View
    {
        return view('colaborador.escanear');
    }

    public function ponto(): View
    {
        $employee = Employee::where('user_id', auth()->id())->first();

        return $employee ? view('time-clock-offline', ['employee' => $employee]) : view('time-clock-sem-ficha');
    }

    /** Tela mostrada pelo service worker quando não há internet e a página pedida nunca foi aberta. */
    public function offline(): View
    {
        return view('colaborador.offline');
    }

    public function manifest(): JsonResponse
    {
        return response()->json([
            'id' => '/',
            'name' => 'Oravel Colaborador',
            'short_name' => 'Oravel',
            'start_url' => route('colab.home', [], false),
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#09090b',
            'theme_color' => '#09090b',
            'icons' => [
                ['src' => asset('icon.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }
}
