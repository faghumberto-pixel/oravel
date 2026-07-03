<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantSlug = $request->route('tenantSlug');

        if (!$tenantSlug) {
            return $next($request);
        }

        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if (!$tenant) {
            abort(404, 'Tenant not found.');
        }

        app()->instance('tenant', $tenant);

        return $next($request);
    }
}
