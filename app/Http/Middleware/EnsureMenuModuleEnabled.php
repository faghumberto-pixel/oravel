<?php

namespace App\Http\Middleware;

use App\Support\MenuModules;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia a URL direta de um menu/página que o contrato do cliente não
 * libera (o menu já some; isto impede abrir digitando o endereço).
 * Usa o segmento depois de /admin/ e as chaves de config/menu_modules.php.
 */
class EnsureMenuModuleEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $chave = MenuModules::keyForSlug($request->segment(2));
        $tenant = Tenancy::current();

        if ($chave && $tenant && ! $tenant->hasFeature($chave)) {
            abort(403, 'Este módulo não está liberado no seu contrato.');
        }

        return $next($request);
    }
}
