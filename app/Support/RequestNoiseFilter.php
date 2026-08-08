<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Regra de "isso nao e' uma pagina de verdade" compartilhada entre
 * middlewares de tracking (TrackSiteVisit, e potencialmente LogUserActivity
 * no futuro). Livewire/assets/health-check nao sao navegacao real -- contar
 * como hit vira ruido de volume absurdo.
 */
class RequestNoiseFilter
{
    public static function isNoise(Request $request): bool
    {
        if (
            $request->hasHeader('X-Livewire') ||
            $request->hasHeader('X-Livewire-Method') ||
            $request->hasHeader('X-Livewire-Component-Name') ||
            str_starts_with($request->path(), 'livewire/')
        ) {
            return true;
        }

        if (preg_match('#\.(js|css|png|jpg|jpeg|svg|ico|woff2?|map)$#', $request->path())) {
            return true;
        }

        if (str_starts_with($request->path(), 'api/')) {
            return true;
        }

        if (in_array($request->path(), ['up', 'favicon.ico', 'robots.txt', 'sitemap.xml'], true)) {
            return true;
        }

        return false;
    }
}
