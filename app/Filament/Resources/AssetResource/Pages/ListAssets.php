<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\AssetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Facades\Filament;

#[BelongsToFeature('assets')]
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

    /**
     * ATENÇÃO: Descomente os widgets APENAS após garantir que os arquivos 
     * app/Filament/Widgets/AssetUtilizationStats.php 
     * e app/Filament/Widgets/AssetStatusChart.php realmente existam.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            // AssetUtilizationStats::class, // Comentado para evitar erro 500
            // AssetStatusChart::class,      // Comentado para evitar erro 500
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return 2;
    }
}
