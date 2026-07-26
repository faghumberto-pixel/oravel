<?php

namespace App\Filament\Resources\PurchaseOrderResource\Widgets;

use App\Models\PurchaseOrder;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PurchaseOrderStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $total = PurchaseOrder::count();

        $valorTotal = PurchaseOrder::sum('total_value');

        $abertas = PurchaseOrder::where('status', PurchaseOrder::STATUS_ABERTA)->count();

        $recebidas = PurchaseOrder::where('status', PurchaseOrder::STATUS_RECEBIDA)->count();

        return [
            Stat::make('Total de Ordens', $total)
                ->description('Todas cadastradas')
                ->color('gray'),

            Stat::make('Valor Total', 'R$ '.number_format((float) $valorTotal, 2, ',', '.'))
                ->description('Soma de todas as ordens')
                ->color('info'),

            Stat::make('Abertas', $abertas)
                ->description('Aguardando recebimento')
                ->color($abertas > 0 ? 'warning' : 'success'),

            Stat::make('Recebidas', $recebidas)
                ->description('Recebimento concluído')
                ->color('success'),
        ];
    }
}
