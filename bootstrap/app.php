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
        // 🟢 Mantém o seu rastreador de presença na pilha web padrão do Laravel 12
        $middleware->web(append: [
            \App\Http\Middleware\UpdateUserLastSeen::class,
        ]);

        // 🔒 REGISTRO SUPREMO: Adiciona o apelido do novo middleware de segurança do Oravel
        $middleware->alias([
            'redirecionar.tecnico' => \App\Http\Middleware\RedirectTechnicianFromDashboard::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    // Força o carregamento dos provedores essenciais e das novas amarras de segurança
    ->registered(function ($app) {
        $app->register(\Illuminate\Auth\AuthServiceProvider::class);
        $app->register(\App\Providers\AuthServiceProvider::class); // 🔒 ATIVADO: Interceptador de Gates atado ao núcleo
    })
    ->create();