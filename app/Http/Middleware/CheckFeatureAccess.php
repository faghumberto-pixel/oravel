<?php

namespace App\Http\Middleware;

use App\Services\FeatureDiscoveryService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CheckFeatureAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        if (!$user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = $user->tenant;
        if (!$tenant) {
            return $next($request);
        }

        // Extrai resource da URL: /admin/assets → AssetResource
        if (preg_match('/\/admin\/([a-z-]+)/', $request->path(), $matches)) {
            $resourceName = Str::studly(str_replace('-', '_', $matches[1])) . 'Resource';
            $resourceClass = "App\\Filament\\Resources\\$resourceName";

            if (class_exists($resourceClass)) {
                $featureMapping = FeatureDiscoveryService::getAllFeatureMapping();
                $featureSlug = $featureMapping[$resourceClass] ?? null;

                if ($featureSlug && !$tenant->hasFeature($featureSlug)) {
                    abort(403, "Você não tem acesso ao módulo: $featureSlug");
                }
            }
        }

        return $next($request);
    }
}
