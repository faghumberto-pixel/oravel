<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * Middleware that sets up the central panel AND redirects to central login if unauthenticated.
 * This combines SetUpPanel + redirect logic in one middleware to avoid
 * middleware ordering issues where Filament's Authenticate middleware
 * would redirect to the default panel's login URL (/admin/login) instead
 * of the current panel's login URL (/central/login).
 */
class CentralPanelAuth
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Set up the central panel
        $panel = Filament::getPanel('central');
        Filament::setCurrentPanel($panel);
        Filament::bootCurrentPanel();

        // Check authentication using Filament's auth guard
        $guard = Filament::auth();

        if (! $guard->check()) {
            return redirect($panel->getLoginUrl());
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        /** @var \Illuminate\Database\Eloquent\Model $user */
        $user = $guard->user();

        abort_if(
            $user instanceof \Filament\Models\Contracts\FilamentUser ?
                (! $user->canAccessPanel($panel)) :
                (config('app.env') !== 'local'),
            403,
        );

        return $next($request);
    }
}