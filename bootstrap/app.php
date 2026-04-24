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
        // Nada aqui, deixe o padrão do Laravel 12
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    // Adicione esta linha abaixo para forçar o carregamento dos provedores essenciais:
    ->registered(function ($app) {
        $app->register(\Illuminate\Auth\AuthServiceProvider::class);
    })
    ->create();