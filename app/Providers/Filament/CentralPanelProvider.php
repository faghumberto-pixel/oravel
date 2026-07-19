<?php

namespace App\Providers\Filament;

use App\Filament\Central\Pages\Programacao;
use App\Filament\Central\Resources\PlanResource;
use App\Filament\Central\Widgets\RevenueChart;
use App\Filament\Central\Widgets\SaaSStatsOverview;
use App\Filament\Central\Widgets\SalesCrmStatsWidget;
use App\Filament\Central\Widgets\SalesLeadMapWidget;
use App\Filament\Resources\RoleResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
// Importações corretas dos seus arquivos
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;
use Jeffgreco13\FilamentBreezy\Pages\MyProfilePage;

class CentralPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('central')
            ->path('central')
            ->login()
            ->colors(['primary' => Color::Blue])
            ->favicon(asset('favicon.png'))
            ->resources([
                PlanResource::class,
                RoleResource::class,
            ])
            ->discoverResources(in: app_path('Filament/Central/Resources'), for: 'App\\Filament\\Central\\Resources')
            ->pages([
                Pages\Dashboard::class,
                Programacao::class,
            ])
            ->widgets([
                SaaSStatsOverview::class, // <-- NOME CORRETO E IMPORTADO LÁ EM CIMA
                RevenueChart::class,
                SalesCrmStatsWidget::class,
                SalesLeadMapWidget::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('8s')
            ->plugin(
                // central so e acessado por super admins (ver CLAUDE.md) -- forcar 2FA
                // aqui equivale a forcar 2FA so pra eles, sem logica extra de role.
                BreezyCore::make()
                    ->myProfile()
                    ->enableTwoFactorAuthentication(force: true)
            )
            ->userMenuItems([
                // chave != 'account' de proposito -- ver comentario em AdminPanelProvider
                'my_profile_link' => MenuItem::make()
                    ->label('Minha Conta')
                    ->icon('heroicon-o-user-circle')
                    ->url(fn () => MyProfilePage::getUrl(panel: 'central')),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
