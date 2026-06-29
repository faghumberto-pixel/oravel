<?php

namespace App\Filament\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FilterResourcesByFeatures
{
    public function handle(Request $request, Closure $next)
    {
        if (Filament::getCurrentPanel()?->getId() !== 'admin') {
            return $next($request);
        }

        $user = Auth::user();
        if (!$user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = $user->tenant;
        if (!$tenant) {
            return $next($request);
        }

        return $next($request);
    }
}
