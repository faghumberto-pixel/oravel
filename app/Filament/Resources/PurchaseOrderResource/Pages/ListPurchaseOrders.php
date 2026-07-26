<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\PurchaseOrderExporter;
use App\Filament\Resources\PurchaseOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseOrders extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nova Ordem de Compra (Avulsa)'),
            $this->printAction(),
            Actions\ExportAction::make()->exporter(PurchaseOrderExporter::class),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PurchaseOrderResource\Widgets\PurchaseOrderStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
