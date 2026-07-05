<?php

namespace App\Filament\Resources\SolicitacaoLocacaoResource\Widgets;

use App\Models\SolicitacaoLocacao;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SolicitacaoLocacaoStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $total = SolicitacaoLocacao::count();
        $emAndamento = SolicitacaoLocacao::where('status_comercial', 'proposta_em_andamento')->count();

        $fechadasNoMes = SolicitacaoLocacao::where('status_comercial', 'contrato_fechado')
            ->where('updated_at', '>=', now()->startOfMonth())
            ->count();

        $totalNoMes = SolicitacaoLocacao::where('created_at', '>=', now()->startOfMonth())->count();
        $canceladasNoMes = SolicitacaoLocacao::where('status_comercial', 'cancelado')
            ->where('updated_at', '>=', now()->startOfMonth())
            ->count();

        $taxaConversao = $totalNoMes > 0 ? (($totalNoMes - $canceladasNoMes) / $totalNoMes) * 100 : 0;

        return [
            Stat::make('Total de Solicitações', $total)
                ->description('Todos os status')
                ->color('gray'),

            Stat::make('Em Andamento', $emAndamento)
                ->description('Propostas abertas')
                ->color('warning'),

            Stat::make('Fechadas no Mês', $fechadasNoMes)
                ->description(now()->translatedFormat('F/Y'))
                ->color('success'),

            Stat::make('Taxa de Conversão', number_format($taxaConversao, 1).'%')
                ->description('Não canceladas no mês')
                ->color($taxaConversao >= 70 ? 'success' : 'danger'),
        ];
    }
}
