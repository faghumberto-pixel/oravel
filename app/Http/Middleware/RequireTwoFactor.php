<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 2FA obrigatorio para todos os usuarios dos paineis admin e central.
 *
 * Quem ainda nao confirmou o 2FA so alcanca "Minha Conta" (para configurar) e o
 * logout. Excecao individual: users.two_factor_exempt (definida so na Central).
 * Desligamento global de emergencia: config('oravel.require_2fa') = false.
 */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();
        $panel = Filament::getCurrentPanel();

        if (! $user || ! $panel || ! config('oravel.require_2fa', true)) {
            return $next($request);
        }

        if ($user->two_factor_exempt || $user->hasConfirmedTwoFactor()) {
            return $next($request);
        }

        $routeName = (string) $request->route()?->getName();
        $profileRoute = 'filament.'.$panel->getId().'.pages.my-profile';

        if ($routeName === $profileRoute || str_contains($routeName, 'logout')) {
            return $next($request);
        }

        Notification::make()
            ->title('Ative a verificação em duas etapas para continuar')
            ->body('Por segurança, o acesso ao sistema exige o aplicativo autenticador.')
            ->warning()
            ->send();

        return redirect()->route($profileRoute);
    }
}
