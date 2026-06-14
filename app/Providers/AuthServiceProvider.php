<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Mapeamento de Policies da aplicacao.
     *
     * Removidos os mapeamentos para SaaSResourcePolicy: Supplier e
     * SolicitacaoLocacao agora caem na DynamicPolicy (via guessPolicyNamesUsing
     * no AppServiceProvider), que delega a AbstractPolicy + SaaSRegistry.
     */
    protected $policies = [];

    public function boot(): void
    {
        $this->registerPolicies();

        // Gate::before removido: era um interceptador que desviava Supplier e
        // SolicitacaoLocacao para a SaaSResourcePolicy (lista-branca invertida
        // que liberava modelos fora do match). A autorizacao volta a ser unica,
        // passando pela AbstractPolicy/registry para todos os modulos.
    }
}
