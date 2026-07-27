<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AnalisePlanoPreventivo;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\PainelPmp;
use App\Filament\Pages\PlantaBaixaAlmoxarifado;
use App\Filament\Pages\RequisicaoReposicaoEstoque;
use App\Filament\Resources\GoodsReceiptResource;
use App\Filament\Resources\MaintenancePlanResource;
use App\Filament\Resources\MaterialCategoryResource;
use App\Filament\Resources\MaterialRequestResource;
use App\Filament\Resources\MaterialResource;
use App\Filament\Resources\MaterialStockTakeResource;
use App\Filament\Resources\PartsRequestResource;
use App\Filament\Resources\PreventiveMaintenanceExecutionResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\StockMovementResource;
use App\Filament\Resources\StorageLocationResource;
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
                // Paleta do artefato "Central de Artefatos" (2026-07-25):
                // laranja de destaque + neutros quentes (stone) no lugar do
                // slate frio, pra bater com o fundo creme/bordas do tema novo.
                'primary' => Color::hex('#ea580c'),
                'gray' => Color::Stone,
                // So' pro status "quarentena" do Ativo (Asset::statusColor()) --
                // os 6 nomes padrao do Filament nao cobrem os 7 status reais.
                'purple' => Color::Purple,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('1.75rem')
            ->favicon(asset('favicon.png'))
            ->navigationGroups([
                NavigationGroup::make('PCM'),
                NavigationGroup::make('Logística'),
                NavigationGroup::make('Ativos e Materiais'),
                NavigationGroup::make('Equipe'),
                NavigationGroup::make('Comercial'),
                NavigationGroup::make('Financeiro'),
                NavigationGroup::make('Relatórios'),
                NavigationGroup::make('Configurações'),
            ])
            ->navigationItems([
                // Agrupa tudo que e' Preventiva dentro de PCM (pedido do
                // usuario 2026-07-26) -- Planos Preventivos, execucoes de
                // Preventiva e o Dashboard PMP (Planejamento de Manutencao
                // Preventiva, ja usava essa sigla) ficavam soltos junto com
                // OS/Kanban/Avarias/etc, um menu so' longo demais.
                NavigationItem::make('PMP')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->group('PCM')
                    ->sort(2)
                    ->visible(fn () => MaintenancePlanResource::canViewAny()
                        || PreventiveMaintenanceExecutionResource::canViewAny()
                        || PainelPmp::canAccess()
                        || AnalisePlanoPreventivo::canAccess())
                    ->childItems([
                        NavigationItem::make('Dashboard PMP')
                            ->url(fn () => PainelPmp::getUrl())
                            ->visible(fn () => PainelPmp::canAccess()),
                        NavigationItem::make('Planos Preventivos')
                            ->url(fn () => MaintenancePlanResource::getUrl())
                            ->visible(fn () => MaintenancePlanResource::canViewAny()),
                        NavigationItem::make('Preventivas')
                            ->url(fn () => PreventiveMaintenanceExecutionResource::getUrl())
                            ->visible(fn () => PreventiveMaintenanceExecutionResource::canViewAny()),
                        NavigationItem::make('Análise IA - Preventivas')
                            ->url(fn () => AnalisePlanoPreventivo::getUrl())
                            ->visible(fn () => AnalisePlanoPreventivo::canAccess()),
                    ]),

                // Antes um unico dropdown "Suprimentos" com 10+ itens
                // misturando operacao diaria de almoxarifado com o ciclo
                // formal de compras -- separado em 2 pra refletir que sao
                // atribuicoes/papeis diferentes (quem repoe estoque fisico
                // nao e' necessariamente quem aprova/cotaOC).
                NavigationItem::make('Almoxarifado')
                    ->icon('heroicon-o-archive-box')
                    ->group('Ativos e Materiais')
                    ->sort(10)
                    ->visible(fn () => MaterialResource::canViewAny()
                        || MaterialCategoryResource::canViewAny()
                        || PartsRequestResource::canViewAny()
                        || MaterialStockTakeResource::canViewAny()
                        || StockMovementResource::canViewAny()
                        || StorageLocationResource::canViewAny()
                        || RequisicaoReposicaoEstoque::canAccess())
                    ->childItems([
                        NavigationItem::make('Materiais')
                            ->url(fn () => MaterialResource::getUrl())
                            ->visible(fn () => MaterialResource::canViewAny()),
                        NavigationItem::make('Categorias de Materiais')
                            ->url(fn () => MaterialCategoryResource::getUrl())
                            ->visible(fn () => MaterialCategoryResource::canViewAny()),
                        NavigationItem::make('Solicitações de Peças')
                            ->url(fn () => PartsRequestResource::getUrl())
                            ->visible(fn () => PartsRequestResource::canViewAny()),
                        NavigationItem::make('Reposição de Estoque')
                            ->url(fn () => RequisicaoReposicaoEstoque::getUrl())
                            ->visible(fn () => RequisicaoReposicaoEstoque::canAccess()),
                        NavigationItem::make('Inventário')
                            ->url(fn () => MaterialStockTakeResource::getUrl())
                            ->visible(fn () => MaterialStockTakeResource::canViewAny()),
                        NavigationItem::make('Histórico de Estoque')
                            ->url(fn () => StockMovementResource::getUrl())
                            ->visible(fn () => StockMovementResource::canViewAny()),
                        NavigationItem::make('Localizações (Planta Baixa)')
                            ->url(fn () => StorageLocationResource::getUrl())
                            ->visible(fn () => StorageLocationResource::canViewAny()),
                        NavigationItem::make('Planta Baixa (Almoxarifado)')
                            ->url(fn () => PlantaBaixaAlmoxarifado::getUrl())
                            ->visible(fn () => PlantaBaixaAlmoxarifado::canAccess()),
                    ]),

                NavigationItem::make('Compras')
                    ->icon('heroicon-o-shopping-cart')
                    ->group('Ativos e Materiais')
                    ->sort(11)
                    ->visible(fn () => SupplierResource::canViewAny()
                        || MaterialRequestResource::canViewAny()
                        || PurchaseOrderResource::canViewAny()
                        || GoodsReceiptResource::canViewAny())
                    ->childItems([
                        NavigationItem::make('Fornecedores')
                            ->url(fn () => SupplierResource::getUrl())
                            ->visible(fn () => SupplierResource::canViewAny()),
                        NavigationItem::make('Requisições de Compra')
                            ->url(fn () => MaterialRequestResource::getUrl())
                            ->visible(fn () => MaterialRequestResource::canViewAny()),
                        NavigationItem::make('Ordens de Compra')
                            ->url(fn () => PurchaseOrderResource::getUrl())
                            ->visible(fn () => PurchaseOrderResource::canViewAny()),
                        NavigationItem::make('Recebimentos')
                            ->url(fn () => GoodsReceiptResource::getUrl())
                            ->visible(fn () => GoodsReceiptResource::canViewAny()),
                    ]),
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn () => view('filament.topbar-brand-and-ticker'),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn () => view('filament.topbar-tenant-switcher'),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn () => view('filament.help-icon'),
            )
            ->renderHook(
                // Busca de tela/menu -- o global search nativo do Filament so'
                // aparece se algum Resource declarar getGloballySearchableAttributes()
                // (nenhum declara hoje), entao a lupa nativa nunca renderiza. Ver
                // App\Livewire\ScreenSearch.
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn () => view('filament.screen-search-mount'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn () => view('filament.acting-tenant-banner'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('filament.login-background'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('filament.brand-header-background'),
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
                PanelsRenderHook::BODY_END,
                fn () => view('filament.keyboard-shortcuts'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.oravel-gauge-chart-plugin'),
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
