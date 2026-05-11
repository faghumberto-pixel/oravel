<?php

namespace App\Providers\Filament;

use App\Models\Tenant;
use App\Filament\Pages\Tenancy\RegisterTenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Support\Facades\Blade;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->tenant(Tenant::class)
            ->tenantRegistration(RegisterTenant::class)
            ->colors(['primary' => Color::Amber])

            // 1. Definição da Ordem dos Grupos de Navegação
            ->navigationGroups([
                NavigationGroup::make('GESTÃO COMERCIAL'),
                NavigationGroup::make('GESTÃO DE ATIVOS'),
                NavigationGroup::make('GESTÃO DE MANUTENÇÃO'),
                NavigationGroup::make('GESTÃO DE MATERIAIS'),
                NavigationGroup::make('GESTÃO FINANCEIRA'),
                NavigationGroup::make('GESTÃO DE PESSOAS'),
                NavigationGroup::make('CONFIGURAÇÕES GERAIS')->collapsed(),
            ])

            // 2. Itens de Menu Manuais
            ->navigationItems([
                NavigationItem::make('Clientes')
                    ->group('GESTÃO COMERCIAL')
                    ->icon('heroicon-o-user-group')
                    ->url(fn () => \App\Filament\Resources\ClientResource::getUrl('index')),
                
                NavigationItem::make('Contratos de Locação')
                    ->group('GESTÃO COMERCIAL')
                    ->icon('heroicon-o-document-text')
                    ->url(fn () => \App\Filament\Resources\ContractResource::getUrl('index')),

                NavigationItem::make('Ativos')
                    ->group('GESTÃO DE ATIVOS')
                    ->icon('heroicon-o-truck')
                    ->url(fn () => \App\Filament\Resources\AssetResource::getUrl('index')),

                NavigationItem::make('Gestão de Checklist')
                    ->group('GESTÃO DE MANUTENÇÃO')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->url(fn () => \App\Filament\Resources\ChecklistTemplateResource::getUrl('index')),
                
                NavigationItem::make('Ordens de Serviço')
                    ->group('GESTÃO DE MANUTENÇÃO')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->url(fn () => \App\Filament\Resources\MaintenanceOrderResource::getUrl('index')),

                NavigationItem::make('Materiais')
                    ->group('GESTÃO DE MATERIAIS')
                    ->icon('heroicon-o-beaker')
                    ->url(fn () => \App\Filament\Resources\MaterialResource::getUrl('index')),
                
                NavigationItem::make('Funcionários')
                    ->group('GESTÃO DE PESSOAS')
                    ->icon('heroicon-o-identification')
                    ->url(fn () => \App\Filament\Resources\UserResource::getUrl('index')),
            ])

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            
            // O discoverWidgets permanece removido para manter o controle total e evitar erros de cache
            ->pages([
                Pages\Dashboard::class,
            ])

            // 3. Registro Explícito de Widgets (Incluindo o novo AssetUtilizationStats)
            ->widgets([
                \App\Filament\Widgets\StatsOverview::class,
                \App\Filament\Widgets\AssetUtilizationStats::class, // Widget recriado e funcional
                \App\Filament\Widgets\OperationalAlerts::class,
                \App\Filament\Widgets\FleetStatusChart::class,
                \App\Filament\Widgets\AssetStatusChart::class,
                \App\Filament\Widgets\MaintenanceAlertChart::class,
                \App\Filament\Widgets\RecentContractsTable::class,
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
            ])
            
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => Blade::render('
                    <div class="flex items-center px-4 h-12">
                        <span class="text-sm font-black text-amber-500 uppercase tracking-tighter mr-4">
                            ORAVEL SYSTEM
                        </span>
                    </div>
                ')
            );
    }
}