<?php

use App\Http\Middleware\BlockBannedIps;
use App\Http\Middleware\ColaboradorAutenticado;
use App\Http\Middleware\ColaboradorSomenteCelular;
use App\Http\Middleware\EnsureAcademyAccess;
use App\Http\Middleware\RedirectGuestToChatLogin;
use App\Http\Middleware\RedirectTechnicianFromDashboard;
use App\Http\Middleware\TrackSiteVisit;
use App\Http\Middleware\UpdateUserLastSeen;
use App\Support\CloudflareProxies;
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // colaborador.php vem ANTES de web.php de propósito: o Laravel casa rotas por ordem de registro, e a rota
        // '/' do site principal (qualquer host) capturaria o subdomínio do app do colaborador.
        web: [__DIR__.'/../routes/colaborador.php', __DIR__.'/../routes/web.php'],
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // IP real do visitante atrás do Cloudflare: só confia no X-Forwarded-For
        // quando a conexão vem de uma faixa do Cloudflare (ver CloudflareProxies).
        // Só o cabeçalho FOR é confiado, de propósito: esquema/host/porta ficam
        // como estavam, sem mudar a geração de URLs.
        $middleware->trustProxies(
            at: CloudflareProxies::RANGES,
            headers: Request::HEADER_X_FORWARDED_FOR,
        );

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
            'academy.access' => EnsureAcademyAccess::class,
            'colab.mobile' => ColaboradorSomenteCelular::class,
            'colab.auth' => ColaboradorAutenticado::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // App do colaborador: um 419 (token velho) quase sempre é toque duplo no "Entrar" -- o 1º envio já logou
        // e renovou a sessão. Em vez de mostrar "Página expirada", leva ao início (se logado) ou à tela de entrada.
        $exceptions->render(function (HttpException $e, Request $request) {
            // O Laravel já converteu o TokenMismatchException em HttpException 419 neste ponto.
            if ($e->getStatusCode() !== 419 || ! $request->routeIs('colab.*')) {
                return null;
            }

            return $request->user()
                ? redirect()->route('colab.home')
                : redirect()->route('colab.login')->withErrors(['email' => 'A página expirou. Tente entrar de novo.']);
        });
    })
    // Força o carregamento dos provedores essenciais e das novas amarras de segurança
    ->registered(function ($app) {
        $app->register(AuthServiceProvider::class);
        $app->register(App\Providers\AuthServiceProvider::class); // 🔒 ATIVADO: Interceptador de Gates atado ao núcleo
    })
    ->create();
