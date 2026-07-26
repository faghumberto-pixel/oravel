<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\ClientExporter;
use App\Filament\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClients extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->printAction(),
            Actions\ExportAction::make()->exporter(ClientExporter::class),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClientResource\Widgets\ClientStats::class,
            ClientResource\Widgets\ClientActiveContractGaugeWidget::class,
            ClientResource\Widgets\NewClientsTrendWidget::class,
            ClientResource\Widgets\ContractsStartedVsEndedAreaWidget::class,
            ClientResource\Widgets\ClientsByNicheChartWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
