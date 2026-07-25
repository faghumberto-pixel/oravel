<?php

namespace App\Filament\Resources\CrmLeadResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Resources\CrmLeadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCrmLeads extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = CrmLeadResource::class;

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
            CrmLeadResource\Widgets\CrmLeadStats::class,
            CrmLeadResource\Widgets\CrmLeadsCreatedTrendWidget::class,
            CrmLeadResource\Widgets\ConversionRateGaugeWidget::class,
            CrmLeadResource\Widgets\LeadsWonVsLostAreaWidget::class,
            CrmLeadResource\Widgets\LeadsBySourceChartWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
