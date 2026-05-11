<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Models\Contract;
use App\Models\Client;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Pega o tenant atual da URL. Se for nulo (Painel Central), usa o tenant do usuário logado.
        $tenant = Filament::getTenant();
        $tenantId = $tenant ? $tenant->id : Auth::user()->tenant_id;

        // Se o usuário for da Oravel e estiver no painel central (sem tenant na URL), 
        // talvez você queira mostrar o global. Caso contrário, filtramos pelo ID.
        $queryAtivos = Asset::query();
        $queryContratos = Contract::query();
        $queryClientes = Client::query();

        if ($tenantId) {
            $queryAtivos->where('tenant_id', $tenantId);
            $queryContratos->where('tenant_id', $tenantId);
            $queryClientes->where('tenant_id', $tenantId);
        }

        // 1. Ocupação e Utilização
        $totalAtivos = (clone $queryAtivos)->count();
        $locados = (clone $queryAtivos)->where('status', 'Locado')->count();
        $taxaUtilizacao = $totalAtivos > 0 ? round(($locados / $totalAtivos) * 100) : 0;

        // 2. Receita Contratual e Ticket Médio
        $contratosAtivos = (clone $queryContratos)->where('status', 'Ativo');
        $receitaPrevista = $contratosAtivos->sum('price');
        $totalContratosAtivos = $contratosAtivos->count();
        $ticketMedio = $totalContratosAtivos > 0 ? $receitaPrevista / $totalContratosAtivos : 0;

        // 3. Novos Clientes (Últimos 30 dias)
        $novosClientes = (clone $queryClientes)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return [
            Stat::make('Receita Contratual Ativa', 'R$ ' . number_format($receitaPrevista, 2, ',', '.'))
                ->description('Total em contratos vigentes')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Ticket Médio', 'R$ ' . number_format($ticketMedio, 2, ',', '.'))
                ->description('Valor médio por locação')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('Taxa de Utilização', "{$taxaUtilizacao}%")
                ->description("{$locados} de {$totalAtivos} ativos locados")
                ->descriptionIcon('heroicon-m-chart-bar-square')
                ->color($taxaUtilizacao > 70 ? 'success' : 'warning'),

            Stat::make('Novos Clientes', $novosClientes)
                ->description('Conquistados nos últimos 30 dias')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('primary'),
        ];
    }
}