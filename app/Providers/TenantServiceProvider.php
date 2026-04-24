<?php

namespace App\Providers;

use App\Contracts\CurrentTenant; // Importe a interface
use App\Services\CurrentTenantService; // Importe a implementação
use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registra a interface para que o Laravel saiba que,
        // quando alguém pedir CurrentTenant::class, deve retornar uma instância de CurrentTenantService.
        $this->app->singleton(CurrentTenant::class, function ($app) {
            return new CurrentTenantService();
        });
    }
/**
 * Bootstrap services.
 */
public function boot(): void
{
    //
}
}