<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\GoodsReceiptResource;
use App\Filament\Resources\MaterialCategoryResource;
use App\Filament\Resources\MaterialRequestResource;
use App\Filament\Resources\MaterialResource;
use App\Filament\Resources\MaterialStockTakeResource;
use App\Filament\Resources\PartsRequestResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\StockMovementResource;
use App\Filament\Resources\SupplierResource;
use App\Http\Middleware\LogUserActivity;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
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
use Jeffgreco13\FilamentBreezy\BreezyCore;
use Jeffgreco13\FilamentBreezy\Pages\MyProfilePage;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->topNavigation()
            ->colors([
                'primary' => Color::Orange,
                'gray' => Color::Slate,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
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
                        || PartsRequestResource::canViewAny()
                        || MaterialRequestResource::canViewAny()
                        || PurchaseOrderResource::canViewAny()
                        || GoodsReceiptResource::canViewAny()
                        || MaterialStockTakeResource::canViewAny()
                        || StockMovementResource::canViewAny())
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
                        NavigationItem::make('Requisições de Compra')
                            ->url(fn () => MaterialRequestResource::getUrl())
                            ->visible(fn () => MaterialRequestResource::canViewAny()),
                        NavigationItem::make('Ordens de Compra')
                            ->url(fn () => PurchaseOrderResource::getUrl())
                            ->visible(fn () => PurchaseOrderResource::canViewAny()),
                        NavigationItem::make('Recebimentos')
                            ->url(fn () => GoodsReceiptResource::getUrl())
                            ->visible(fn () => GoodsReceiptResource::canViewAny()),
                        NavigationItem::make('Inventário')
                            ->url(fn () => MaterialStockTakeResource::getUrl())
                            ->visible(fn () => MaterialStockTakeResource::canViewAny()),
                        NavigationItem::make('Histórico de Estoque')
                            ->url(fn () => StockMovementResource::getUrl())
                            ->visible(fn () => StockMovementResource::canViewAny()),
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
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('filament.bfcache-reload'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.chat-widget-mount'),
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn () => view('filament.panel-footer'),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->databaseNotifications()
            // 15s -> 8s: unico ajuste de "tempo real" que o usuario pediu pra
            // essa fase -- sininho continua sendo polling, nao push de
            // verdade (decisao explicita, ver plano de Suprimentos/Frota).
            ->databaseNotificationsPolling('8s')
            ->plugin(
                FilamentFullCalendarPlugin::make()
                    ->selectable(false)
                    ->editable()
                    ->locale('pt-br')
            )
            ->plugin(
                BreezyCore::make()
                    ->myProfile(hasAvatars: true)
                    ->enableTwoFactorAuthentication()
            )
            ->userMenuItems([
                // Chave != 'account' de proposito: BreezyCore::boot() roda depois de
                // panel() e registra o proprio item na chave 'account' (sem label,
                // por isso o menu mostrava soh o nome do usuario) -- usar a mesma
                // chave faria o nosso ser sobrescrito. Isso soma um segundo atalho,
                // visivel e com rotulo, pra mesma pagina.
                'my_profile_link' => MenuItem::make()
                    ->label('Minha Conta')
                    ->icon('heroicon-o-user-circle')
                    ->url(fn () => MyProfilePage::getUrl()),
            ])
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
