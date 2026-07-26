<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\AssetExporter;
use App\Filament\Resources\AssetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssets extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Novo Ativo'),
            $this->printAction(),
            Actions\ExportAction::make()->exporter(AssetExporter::class),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AssetResource\Widgets\AssetStats::class,
            AssetResource\Widgets\AssetStatusChartWidget::class,
            AssetResource\Widgets\FleetAvailabilityGaugeChartWidget::class,
            AssetResource\Widgets\AssetsCreatedTrendWidget::class,
            AssetResource\Widgets\AssetsByCategoryChartWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
