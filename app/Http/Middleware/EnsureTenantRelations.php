<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use App\Models\Plan;
use Illuminate\Support\Str;

class EnsureTenantRelations
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = Filament::getTenant();

        if ($tenant && !$tenant->relationLoaded('plan')) {
            $planId = (string) $tenant->plan_id;

            if (Str::isUuid($planId)) {
                $plan = Plan::withoutGlobalScopes()->find($planId);
                $tenant->setRelation('plan', $plan);
            } else {
                $tenant->setRelation('plan', null);
            }
        }

        return $next($request);
    }
}