<?php

namespace App\Providers\Filament;

use App\Filament\Client\Pages\Auth\Login;
use App\Http\Middleware\TrackSiteVisit;
use Filament\Http\Middleware\Authenticate;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;

/**
 * Portal do Cliente -- painel separado do admin/central, guard 'client'
 * dedicado (config/auth.php). Fica em app.oravel.com.br/cliente, sem
 * domínio próprio (decisão do usuário 2026-08-25). Namespace de
 * Resources/Pages isolado (app/Filament/Client/*) para nada do admin
 * vazar por auto-discovery.
 *
 * Isolamento de dados: as Pages deste painel NUNCA confiam no global
 * scope de BelongsToTenant (ele resolve Auth::user() no guard 'web', que
 * é null aqui) -- cada query filtra manualmente por tenant_id + client_id
 * do Client autenticado no guard 'client'.
 */
class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('portal-cliente')
            ->path('cliente')
            ->authGuard('client')
            ->login(Login::class)
            ->brandLogo(fn () => view('filament.client.brand-logo'))
            ->brandLogoHeight('1.25rem')
            // Antes o painel abria na Dashboard padrão do Filament (sem
            // nenhum widget registrado -- tela em branco). PainelCliente
            // é a home de verdade agora (pedido do usuário 2026-09-25).
            ->homeUrl(fn () => route('filament.portal-cliente.pages.inicio'))
            ->colors([
                'primary' => Color::hex('#2563eb'),
                'gray' => Color::Stone,
            ])
            ->favicon(asset('favicon.png').'?v=6')
            // Portal sempre no tema claro: as cores do painel (menu #1d2133, centro #fbfbfb,
            // cards brancos) foram desenhadas só para o claro. No escuro os cards ficavam pretos
            // e os títulos sumiam.
            ->darkMode(false)
            ->viteTheme('resources/css/filament/client/theme.css')
            ->discoverResources(in: app_path('Filament/Client/Resources'), for: 'App\\Filament\\Client\\Resources')
            ->discoverPages(in: app_path('Filament/Client/Pages'), for: 'App\\Filament\\Client\\Pages')
            ->discoverWidgets(in: app_path('Filament/Client/Widgets'), for: 'App\\Filament\\Client\\Widgets')
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn () => view('filament.breadcrumb'),
            )
            ->renderHook(
                // Sidebar navy + variáveis de cor da marca, mesma partial
                // do painel admin (CSS puro, sem acoplamento a tenant --
                // pedido do usuário 2026-09-25: "top bar igual o app").
                PanelsRenderHook::HEAD_END,
                fn () => view('filament.brand-header-background'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('filament.client.cores-painel'),
            )
            // Menu sumia para quem já tinha um grupo "vazio" gravado no navegador pela sanfona
            // do admin (mesmo domínio): limpa antes de o Alpine ler.
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn () => new \Illuminate\Support\HtmlString("<script>try{var g=JSON.parse(localStorage.getItem('collapsedGroups')||'[]');if(Array.isArray(g)){var f=g.filter(function(x){return x&&x!=='null'});if(f.length!==g.length)localStorage.setItem('collapsedGroups',JSON.stringify(f));}}catch(e){}</script>"),
            )
            ->renderHook(
                // Sanfona nos grupos do menu, mesmo comportamento do admin
                // (2026-09-24) -- útil aqui também, o portal já tem ~10 itens.
                PanelsRenderHook::BODY_END,
                fn () => view('filament.sidebar-accordion'),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn () => view('filament.client.auth.magic-link-hint'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('filament.client.login-background'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                SubstituteBindings::class,
                TrackSiteVisit::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
