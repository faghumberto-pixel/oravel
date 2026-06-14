<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // Bypass total: desabilitamos a lógica de redirecionamento 
        // para quebrar o loop e permitir que o Filament gerencie a sessão.
        return $next($request);
    }
}