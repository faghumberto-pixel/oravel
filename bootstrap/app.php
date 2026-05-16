<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Injeta o rastreador de presença na pilha web padrão do Laravel 12
        $middleware->web(append: [
            \App\Http\Middleware\UpdateUserLastSeen::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    // Adicione esta linha abaixo para forçar o carregamento dos provedores essenciais:
    ->registered(function ($app) {
        $app->register(\Illuminate\Auth\AuthServiceProvider::class);
    })
    ->create();