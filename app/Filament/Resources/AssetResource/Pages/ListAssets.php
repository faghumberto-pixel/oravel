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
            Actions\CreateAction::make()
                ->label('Novo Ativo'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        // Certifique-se de que estes widgets existem no diretório de Widgets
        return [
            AssetUtilizationStats::class,
            AssetStatusChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return 2;
    }
}