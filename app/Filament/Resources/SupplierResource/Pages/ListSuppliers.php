<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = SupplierResource::class;

    protected static ?string $title = 'Fornecedores';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->printAction(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SupplierResource\Widgets\SupplierStats::class,
            SupplierResource\Widgets\SupplierComplianceGaugeWidget::class,
            SupplierResource\Widgets\SuppliersCreatedTrendWidget::class,
            SupplierResource\Widgets\PurchaseOrdersOpenVsReceivedAreaWidget::class,
            SupplierResource\Widgets\TopSuppliersByMaterialsChartWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
