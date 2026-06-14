<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use App\Policies\GlobalTenantFeaturePolicy;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantFeatures
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Filament::getCurrentPanel();
        
        if ($panel) {
            foreach ($panel->getResources() as $resource) {
                $model = $resource::getModel();

                // Registra a Policy Global usando a string da classe (Padrão Laravel)
                // Isso força o Filament a consultar o Gate, sem quebrar o container
                if (Gate::getPolicyFor($model) === null) {
                    Gate::policy($model, GlobalTenantFeaturePolicy::class);
                }
            }
        }

        return $next($request);
    }
}
