<?php

namespace App\Providers\Filament;

use App\Filament\Pages\PainelGestao;
use App\Models\Tenant;
use App\Filament\Pages\Tenancy\RegisterTenant; // ÚNICA IMPORTAÇÃO AQUI
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
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
            // --- ATIVAÇÃO SAAS (MULTI-TENANCY) ---
            ->tenant(Tenant::class)
            ->tenantRegistration(RegisterTenant::class)
            // -------------------------------------
            ->colors(['primary' => Color::Amber])
            ->navigationGroups([
                NavigationGroup::make('GESTAO DE ATIVOS'),
                NavigationGroup::make('GESTAO DE MANUTENÇAO'),
                NavigationGroup::make('GESTAO DE ESTOQUE'),
                NavigationGroup::make('ADMINISTRACAO'),
                NavigationGroup::make('GESTAO DE PESSOAS'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                PainelGestao::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            // --- TOP BAR INTEGRAL REFEITA ---
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => Blade::render('
                    @php
                        $user = auth()->user();
                        $companyName = "Oravel System";
                        $osAbertas = 0;
                        $clientes = 0;
                        $ativosTotal = 0;
                        $dataAcesso = now()->format("d/m/Y H:i");

                        if ($user && $user->tenant_id) {
                            $companyName = \App\Models\Tenant::find($user->tenant_id)?->name ?? "Oravel System";
                            $osAbertas = \App\Models\MaintenanceOrder::where("tenant_id", $user->tenant_id)->whereIn("status", ["Aberto", "Em Andamento"])->count();
                            $clientes = \App\Models\Client::where("tenant_id", $user->tenant_id)->count();
                            $ativosTotal = \App\Models\Asset::where("tenant_id", $user->tenant_id)->count();
                        }
                    @endphp

                    @if($user)
                    <div class="flex items-center w-full justify-between px-4 lg:px-8 h-12" 
                         x-data="{ 
                            active: 0, 
                            messages: [
                                \'🔔 {{ $osAbertas }} OS EM ABERTO\',
                                \'👥 {{ $clientes }} CLIENTES CADASTRADOS\',
                                \'🚜 {{ $ativosTotal }} ATIVOS NA FROTA\',
                                \'📅 ACESSO EM: {{ $dataAcesso }}\'
                            ],
                            timer: null,
                            startTimer() {
                                this.timer = setInterval(() => {
                                    this.active = (this.active + 1) % this.messages.length;
                                }, 4000);
                            }
                         }"
                         x-init="startTimer()"
                         style="min-width: calc(100vw - 350px);">
                        
                        <div class="flex items-center min-w-fit">
                             <span class="text-sm font-black text-amber-500 uppercase tracking-tighter whitespace-nowrap">
                                {{ $companyName }}
                            </span>
                        </div>

                        <div class="hidden md:flex flex-1 justify-center px-4">
                            <div class="h-5 overflow-hidden relative w-full max-w-xs border-x border-gray-700 mx-2">
                                <template x-for="(msg, index) in messages" :key="index">
                                    <div x-show="active === index" 
                                         x-transition:enter="transition ease-out duration-300"
                                         x-transition:enter-start="opacity-0 translate-y-4"
                                         x-transition:enter-end="opacity-100 translate-y-0"
                                         x-transition:leave="transition ease-in duration-300"
                                         x-transition:leave-start="opacity-100 translate-y-0"
                                         x-transition:leave-end="opacity-0 -translate-y-4"
                                         class="text-[11px] font-bold text-gray-400 absolute inset-0 flex items-center justify-center text-center leading-none"
                                         x-text="msg">
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 whitespace-nowrap">
                            <div class="flex flex-col items-end">
                                <span class="text-[9px] uppercase text-gray-500 font-bold leading-none">Usuário</span>
                                <span class="text-xs font-extrabold text-white leading-tight">{{ $user->name }}</span>
                            </div>
                        </div>
                    </div>
                    @endif
                '),
            )
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