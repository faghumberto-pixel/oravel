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
            ->brandName('ORAVEL') 
            ->tenant(Tenant::class)
            ->tenantRegistration(RegisterTenant::class)
            ->colors(['primary' => Color::Amber])

            ->databaseNotifications()
            ->databaseNotificationsPolling('4s')

            ->navigationGroups([
                NavigationGroup::make('GESTÃO COMERCIAL'),
                NavigationGroup::make('GESTÃO DE ATIVOS'),
                NavigationGroup::make('GESTÃO DE MANUTENÇÃO'),
                NavigationGroup::make('GESTÃO DE MATERIAIS'),
                NavigationGroup::make('GESTÃO FINANCEIRA'),
                NavigationGroup::make('GESTÃO DE PESSOAS'),
                NavigationGroup::make('CONFIGURAÇÕES GERAIS')->collapsed(),
            ])

            ->navigationItems([
                // QUADRO DE PÁTIO (KANBAN) - VISÃO ESTRATÉGICA
                NavigationItem::make('Quadro de Pátio (Kanban)')
                    ->group('GESTÃO DE MANUTENÇÃO')
                    ->icon('heroicon-o-squares-2x2')
                    ->sort(1)
                    ->url(fn () => url('/admin/' . \Filament\Facades\Filament::getTenant()->id . '/maintenance-kanban')),

                NavigationItem::make('Clientes')
                    ->group('GESTÃO COMERCIAL')
                    ->icon('heroicon-o-user-group')
                    ->url(fn () => url('/admin/' . \Filament\Facades\Filament::getTenant()->id . '/clients')),
                
                NavigationItem::make('Contratos de Locação')
                    ->group('GESTÃO COMERCIAL')
                    ->icon('heroicon-o-document-text')
                    ->url(fn () => url('/admin/' . \Filament\Facades\Filament::getTenant()->id . '/contracts')),

                NavigationItem::make('Ativos')
                    ->group('GESTÃO DE ATIVOS')
                    ->icon('heroicon-o-truck')
                    ->url(fn () => url('/admin/' . \Filament\Facades\Filament::getTenant()->id . '/assets')),

                NavigationItem::make('Gestão de Checklist')
                    ->group('GESTÃO DE MANUTENÇÃO')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->url(fn () => url('/admin/' . \Filament\Facades\Filament::getTenant()->id . '/checklist-templates')),
                
                NavigationItem::make('Ordens de Serviço')
                    ->group('GESTÃO DE MANUTENÇÃO')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->url(fn () => url('/admin/' . \Filament\Facades\Filament::getTenant()->id . '/maintenance-orders')),

                NavigationItem::make('Materiais')
                    ->group('GESTÃO DE MATERIAIS')
                    ->icon('heroicon-o-beaker')
                    ->url(fn () => url('/admin/' . \Filament\Facades\Filament::getTenant()->id . '/materials')),
                
                NavigationItem::make('Funcionários')
                    ->group('GESTÃO DE PESSOAS')
                    ->icon('heroicon-o-identification')
                    ->url(fn () => url('/admin/' . \Filament\Facades\Filament::getTenant()->id . '/users')),
            ])

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            
            ->pages([
                Pages\Dashboard::class,
                \App\Filament\Pages\MaintenanceKanban::class,
                \App\Filament\Pages\Chat::class,
            ])

            ->widgets([
                \App\Filament\Widgets\StatsOverview::class,
                \App\Filament\Widgets\AssetUtilizationStats::class,
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
                    <div class="hidden lg:flex items-center gap-4 ml-4 text-sm font-medium">
                        <span class="text-amber-500 font-black uppercase tracking-tighter mr-2">ORAVEL</span>
                        <span class="text-gray-400">|</span>
                        <span class="text-gray-500 dark:text-gray-400">
                            Bem-vindo à <strong class="text-gray-900 dark:text-gray-100">{{ \Filament\Facades\Filament::getTenant()?->name }}</strong>
                        </span>
                        <span class="text-gray-300 dark:text-gray-700">|</span>
                        <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400 font-mono" 
                             x-data="{ now: \'\', update() { 
                                const d = new Date(); 
                                this.now = d.toLocaleDateString(\'pt-BR\') + \' - \' + d.toLocaleTimeString(\'pt-BR\', {hour: \'2-digit\', minute:\'2-digit\', second:\'2-digit\'}) 
                             } }" 
                             x-init="update(); setInterval(() => update(), 1000)">
                            <svg class="w-4 h-4 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span x-text="now"></span>
                        </div>
                    </div>
                '),
            );
    }
}