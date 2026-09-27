<?php

use App\Http\Middleware\BlockBannedIps;
use App\Http\Middleware\RedirectGuestToChatLogin;
use App\Http\Middleware\RedirectTechnicianFromDashboard;
use App\Http\Middleware\TrackSiteVisit;
use App\Http\Middleware\UpdateUserLastSeen;
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Sem isso, `auth:sanctum` nas rotas de sync offline (time-clocks,
        // hour-meters) nunca autentica via cookie de sessão do painel --
        // só aceitaria um token Bearer de verdade, que o JS mobile nunca
        // envia (ele reusa a sessao web). Achado 27/09/2026 testando o
        // Ponto Eletronico: POST /api/v1/time-clocks/sync sempre 401.
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        // 🟢 Mantém o seu rastreador de presença na pilha web padrão do Laravel 12
        $middleware->web(append: [
            UpdateUserLastSeen::class,
            TrackSiteVisit::class,
        ]);

        // Bloqueio de IP (pedido do usuário 2026-09-27) -- prepend pra
        // rodar ANTES de tudo o mais na pilha web (rotas públicas, painéis).
        $middleware->web(prepend: [
            BlockBannedIps::class,
        ]);

        // 🔒 REGISTRO SUPREMO: Adiciona o apelido do novo middleware de segurança do Oravel
        $middleware->alias([
            'redirecionar.tecnico' => RedirectTechnicianFromDashboard::class,
            'chat.auth' => RedirectGuestToChatLogin::class,
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
