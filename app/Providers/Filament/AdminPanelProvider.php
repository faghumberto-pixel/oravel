<?php

namespace App\Providers\Filament; // <-- ERA ISSO QUE ESTAVA FALTANDO!

use App\Models\Tenant;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // 1. Garante a criação da rota de login (GET e POST) na base do path (/admin/login)
            ->login() 
            
            // 2. Registra o multi-tenancy apontando para o seu model e o campo de slug
            ->tenant(Tenant::class, slugAttribute: 'slug')
            
            // 3. A SOLUÇÃO: Isola as rotas do tenant. 
            // Agora o login fica em /admin/login e o dashboard em /admin/app/{slug}
            // Isso impede a colisão do wildcard {tenant} com a palavra "login"
            ->tenantRoutePrefix('app') 
            
            ->colors([
                'primary' => \Filament\Support\Colors\Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                \Filament\Widgets\AccountWidget::class,
            ])
            ->middleware([
                \Illuminate\Cookie\Middleware\EncryptCookies::class,
                \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                \Illuminate\Session\Middleware\StartSession::class,
                \Illuminate\Session\Middleware\AuthenticateSession::class,
                \Illuminate\View\Middleware\ShareErrorsFromSession::class,
                \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
                \Filament\Http\Middleware\DisableBladeIconComponents::class,
                \Filament\Http\Middleware\DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                \Filament\Http\Middleware\Authenticate::class,
            ]);
    }
}