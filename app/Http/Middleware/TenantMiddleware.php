// app/Http/Middleware/TenantMiddleware.php
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
        // --- LINHA DE DEPURACAO COMENTADA ---
        // dd('TenantMiddleware está sendo executado!');
        // --- FIM LINHA DE DEPURACAO ---
    $tenantSlug = $request-&gt;route('tenantSlug');

    if (!$tenantSlug) {
        return $next($request);
    }

    $tenant = Tenant::where('slug', $tenantSlug)-&gt;first();

    if (!$tenant) {
        abort(404, 'Tenant not found.');
    }

    app()-&gt;instance('tenant', $tenant);

    return $next($request);
}
}