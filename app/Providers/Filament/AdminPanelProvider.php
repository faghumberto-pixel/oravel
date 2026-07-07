<?php

namespace App\Providers\Filament;

use App\Filament\Resources\MaterialCategoryResource;
use App\Filament\Resources\MaterialResource;
use App\Filament\Resources\PartsRequestResource;
use App\Filament\Resources\SupplierResource;
use App\Http\Middleware\LogUserActivity;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->topNavigation()
            ->colors([
                'primary' => Color::Orange,
                'gray' => Color::Slate,
            ])
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('1.75rem')
            ->favicon(asset('favicon.png'))
            ->navigationGroups([
                NavigationGroup::make('Manutenção'),
                NavigationGroup::make('Logística'),
                NavigationGroup::make('Ativos e Materiais'),
                NavigationGroup::make('Equipe'),
                NavigationGroup::make('Comercial'),
                NavigationGroup::make('Financeiro'),
                NavigationGroup::make('Relatórios'),
                NavigationGroup::make('Configurações'),
            ])
            ->navigationItems([
                NavigationItem::make('Suprimentos')
                    ->icon('heroicon-o-archive-box')
                    ->group('Ativos e Materiais')
                    ->sort(10)
                    ->visible(fn () => MaterialResource::canViewAny()
                        || MaterialCategoryResource::canViewAny()
                        || SupplierResource::canViewAny()
                        || PartsRequestResource::canViewAny())
                    ->childItems([
                        NavigationItem::make('Materiais')
                            ->url(fn () => MaterialResource::getUrl())
                            ->visible(fn () => MaterialResource::canViewAny()),
                        NavigationItem::make('Categorias de Materiais')
                            ->url(fn () => MaterialCategoryResource::getUrl())
                            ->visible(fn () => MaterialCategoryResource::canViewAny()),
                        NavigationItem::make('Fornecedores')
                            ->url(fn () => SupplierResource::getUrl())
                            ->visible(fn () => SupplierResource::canViewAny()),
                        NavigationItem::make('Solicitações de Peças')
                            ->url(fn () => PartsRequestResource::getUrl())
                            ->visible(fn () => PartsRequestResource::canViewAny()),
                    ]),
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn () => view('filament.topbar-tenant-switcher'),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn () => view('filament.help-icon'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn () => view('filament.acting-tenant-banner'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn () => view('filament.announcement-banner'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('filament.login-background'),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('15s')
            ->plugin(
                FilamentFullCalendarPlugin::make()
                    ->selectable(false)
                    ->editable()
                    ->locale('pt-br')
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                SubstituteBindings::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                LogUserActivity::class,
            ]);
    }
}
