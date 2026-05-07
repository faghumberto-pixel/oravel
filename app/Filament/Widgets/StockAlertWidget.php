<?php

namespace App\Filament\Widgets;

use App\Models\StockItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StockAlertWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Contagem de itens que atingiram o estoque mínimo (Alerta)
        $criticalItems = StockItem::whereColumn('current_stock', '<=', 'min_stock')->count();

        return [
            Stat::make('Itens Críticos', $criticalItems)
                ->description('Peças com estoque abaixo do mínimo')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
            Stat::make('Total de Peças Cadastradas', StockItem::count())
                ->description('Volume total em estoque')
                ->color('primary'),
        ];
    }
}