<?php

namespace App\Filament\Resources\MaterialResource\Widgets;

use App\Models\Material;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class MaterialStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $total = Material::count();

        $valorEmEstoque = Material::query()->select(DB::raw('SUM(current_stock * unit_cost) as total'))->value('total');

        $abaixoDoMinimo = Material::whereColumn('current_stock', '<', 'min_stock')->count();

        $semFornecedor = Material::whereNull('supplier_id')->count();

        return [
            Stat::make('Total de Materiais', $total)
                ->description('Itens cadastrados')
                ->color('gray'),

            Stat::make('Valor em Estoque', 'R$ '.number_format((float) $valorEmEstoque, 2, ',', '.'))
                ->description('Custo unitário x quantidade')
                ->color('info'),

            Stat::make('Abaixo do Estoque Mínimo', $abaixoDoMinimo)
                ->description('Risco de ruptura')
                ->color($abaixoDoMinimo > 0 ? 'danger' : 'success'),

            Stat::make('Sem Fornecedor Homologado', $semFornecedor)
                ->description('Pendente de homologação')
                ->color($semFornecedor > 0 ? 'warning' : 'success'),
        ];
    }
}
