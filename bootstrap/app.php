<?php

use App\Http\Middleware\RedirectTechnicianFromDashboard;
use App\Http\Middleware\UpdateUserLastSeen;
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;

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
        // Diagnostico temporario -- 419/TokenMismatchException nao e logado
        // por padrao pelo Laravel, e o 419 recorrente na Programacao (central)
        // nao reproduziu num teste simulando a mesma sessao/token em DEV.
        // Log so' pra capturar o token real enviado x token da sessao na
        // proxima ocorrencia real em PROD -- remover depois de diagnosticado.
        $exceptions->report(function (TokenMismatchException $e) {
            Log::warning('419 TokenMismatch capturado', [
                'url' => request()->fullUrl(),
                'referer' => request()->header('referer'),
                'session_id' => request()->hasSession() ? request()->session()->getId() : null,
                'session_token' => request()->hasSession() ? request()->session()->token() : null,
                'header_csrf_token' => request()->header('X-CSRF-TOKEN'),
                'header_xsrf_token' => request()->header('X-XSRF-TOKEN'),
                'input_token' => request()->input('_token'),
                'cookie_session' => request()->cookie(config('session.cookie')),
                'user_id' => auth()->id(),
            ]);
        });
    })
    // Força o carregamento dos provedores essenciais e das novas amarras de segurança
    ->registered(function ($app) {
        $app->register(AuthServiceProvider::class);
        $app->register(App\Providers\AuthServiceProvider::class); // 🔒 ATIVADO: Interceptador de Gates atado ao núcleo
    })
    ->create();
