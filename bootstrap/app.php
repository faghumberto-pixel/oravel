<?php

use App\Http\Middleware\RedirectTechnicianFromDashboard;
use App\Http\Middleware\UpdateUserLastSeen;
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 🟢 Mantém o seu rastreador de presença na pilha web padrão do Laravel 12
        $middleware->web(append: [
            UpdateUserLastSeen::class,
        ]);

        // 🔒 REGISTRO SUPREMO: Adiciona o apelido do novo middleware de segurança do Oravel
        $middleware->alias([
            'redirecionar.tecnico' => RedirectTechnicianFromDashboard::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    // Força o carregamento dos provedores essenciais e das novas amarras de segurança
    ->registered(function ($app) {
        $app->register(AuthServiceProvider::class);
        $app->register(App\Providers\AuthServiceProvider::class); // 🔒 ATIVADO: Interceptador de Gates atado ao núcleo
    })
    ->create();
