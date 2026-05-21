<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Importações corretas dos seus arquivos
use App\Filament\Central\Widgets\SaaSStatsOverview;
use App\Filament\Central\Widgets\RevenueChart;
use App\Filament\Central\Resources\PlanResource;
use App\Filament\Resources\RoleResource;

class CentralPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('central')
            ->path('central') 
            ->login() 
            ->colors(['primary' => Color::Blue])
            ->resources([
                PlanResource::class,
                RoleResource::class,
            ])
            ->discoverResources(in: app_path('Filament/Central/Resources'), for: 'App\\Filament\\Central\\Resources')
            ->pages([
                Pages\Dashboard::class, 
            ])
            ->widgets([
                SaaSStatsOverview::class, // <-- NOME CORRETO E IMPORTADO LÁ EM CIMA
                RevenueChart::class,
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