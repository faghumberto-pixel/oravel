<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

// Importe seus Models e suas Policies
use App\Models\MaintenanceOrder;
use App\Policies\MaintenanceOrderPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * O mapeamento explícito é a forma mais profissional e rápida de autorização.
     * O Laravel não perde tempo "adivinhando" onde a policy está.
     */
    protected $policies = [
        MaintenanceOrder::class => MaintenanceOrderPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        /**
         * 🛡️ Regra de Ouro: Admin Master tem acesso a TUDO.
         * Isso elimina a necessidade de checar permissões dentro dos controllers/resources
         * para o seu usuário administrador.
         */
        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
                return true;
            }
        });
    }
}