<?php

namespace App\Providers\Filament;

use App\Filament\Client\Pages\Auth\Login;
use App\Http\Middleware\TrackSiteVisit;
use Filament\Http\Middleware\Authenticate;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
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
            ->colors([
                'primary' => Color::hex('#ea580c'),
                'gray' => Color::Stone,
            ])
            ->favicon(asset('favicon.png').'?v=5')
            ->discoverResources(in: app_path('Filament/Client/Resources'), for: 'App\\Filament\\Client\\Resources')
            ->discoverPages(in: app_path('Filament/Client/Pages'), for: 'App\\Filament\\Client\\Pages')
            ->discoverWidgets(in: app_path('Filament/Client/Widgets'), for: 'App\\Filament\\Client\\Widgets')
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
