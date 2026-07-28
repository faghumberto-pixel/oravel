<?php

namespace App\Filament\Central\Widgets;

use App\Models\Tenant;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SaaSStatsOverview extends BaseWidget
{
    /**
     * Estatisticas globais da plataforma SaaS -- pedido do usuario 2026-07-28
     * ("mesmo padrao [do CRM], mais colorido e vivo"): antes so' tinha 2
     * stats sem icone (Total de Tenants/Usuarios). Agora quebra por status
     * (trial vs ativo), que e' a metrica que realmente importa pra um
     * operador de SaaS decidir onde focar esforco comercial.
     */
    protected function getStats(): array
    {
        $ativos = Tenant::where('status', 'active')->count();
        $trial = Tenant::where('status', 'trial')->count();

        return [
            Stat::make('Total de Tenants', Tenant::count())
                ->description('Empresas cadastradas na plataforma')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),

            Stat::make('Em Teste', $trial)
                ->description('Ainda não converteram pra plano pago')
                ->descriptionIcon('heroicon-m-clock')
                ->color($trial > 0 ? 'warning' : 'success'),

            Stat::make('Ativos', $ativos)
                ->description('Com plano pago em dia')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Total de Usuários', User::count())
                ->description('Usuários globais, todos os tenants')
                ->descriptionIcon('heroicon-m-users')
                ->color('crmPurple'),
        ];
    }
}
