<?php

namespace App\Filament\Resources\QuoteResource\Widgets;

use App\Models\Quote;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QuoteStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $total = Quote::count();

        $valorTotal = Quote::sum('total_value');

        $aguardandoAprovacao = Quote::where('status', Quote::STATUS_ENVIADO)->count();

        $aprovados = Quote::whereIn('status', [Quote::STATUS_APROVADO, Quote::STATUS_CONCLUIDO])->count();

        return [
            Stat::make('Total de Orçamentos', $total)
                ->description('Todos cadastrados')
                ->color('gray'),

            Stat::make('Valor Total Orçado', 'R$ '.number_format((float) $valorTotal, 2, ',', '.'))
                ->description('Soma de todos os orçamentos')
                ->color('info'),

            Stat::make('Aguardando Aprovação', $aguardandoAprovacao)
                ->description('Enviados ao cliente')
                ->color($aguardandoAprovacao > 0 ? 'warning' : 'success'),

            Stat::make('Aprovados', $aprovados)
                ->description('Aprovados ou concluídos')
                ->color('success'),
        ];
    }
}
