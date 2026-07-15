<?php

namespace App\Filament\Pages;

use App\Filament\Exports\AssetMapExporter;
use App\Filament\Resources\AssetResource;
use Filament\Actions;
use Filament\Pages\Page;

/**
 * Pagina dedicada em vez de widget no topo de ListAssets -- o mapa
 * (Leaflet, ~450px de altura) polui demais uma lista que ja tem seus
 * proprios stats no topo (AssetStats).
 */
class MapaEquipamentos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationLabel = 'Mapa de Equipamentos';

    protected static ?string $title = 'Mapa de Equipamentos';

    protected static ?string $slug = 'mapa-equipamentos';

    protected static string $view = 'filament.pages.mapa-equipamentos';

    public static function canAccess(): bool
    {
        return AssetResource::canViewAny();
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()->exporter(AssetMapExporter::class),
        ];
    }
}
