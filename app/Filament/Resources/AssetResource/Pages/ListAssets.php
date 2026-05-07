<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Resources\AssetResource;
use App\Filament\Widgets\AssetUtilizationStats;
use App\Filament\Widgets\AssetStatusChart;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * --- INCLUSÃO MÍNIMA: Painel Analítico no Topo ---
     * Renderiza os widgets de estatísticas e gráficos acima da tabela de ativos.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            AssetUtilizationStats::class,
            AssetStatusChart::class,
        ];
    }

    /**
     * Define o número de colunas para os widgets (2 colunas para ficarem lado a lado)
     */
    public function getHeaderWidgetsColumns(): int | string | array
    {
        return 2;
    }
}