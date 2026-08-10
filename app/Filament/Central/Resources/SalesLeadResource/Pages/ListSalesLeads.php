<?php

namespace App\Filament\Central\Resources\SalesLeadResource\Pages;

use App\Filament\Central\Resources\SalesLeadResource;
use App\Filament\Central\Resources\SalesLeadResource\Widgets\LeadsByStageChart;
use App\Filament\Central\Resources\SalesLeadResource\Widgets\SalesLeadListStats;
use App\Filament\Central\Widgets\LeadsBySegmentChart;
use App\Filament\Central\Widgets\LeadsBySourceChart;
use App\Filament\Central\Widgets\LeadsCreatedTrendChart;
use App\Filament\Central\Widgets\ProspectingMapWidget;
use App\Filament\Central\Widgets\SalesLeadMapWidget;
use App\Filament\Central\Widgets\WonLostTrendChart;
use App\Filament\Exports\SalesLeadExporter;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalesLeads extends ListRecords
{
    protected static string $resource = SalesLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()->exporter(SalesLeadExporter::class),
            Actions\CreateAction::make(),
        ];
    }

    /**
     * 9 widgets acima do grid (pedido do usuario 2026-08-10: cards e
     * graficos como o padrao do app -- ver ListAssets -- no minimo 8).
     * Os 2 mapas ja existem no Dashboard CRM, reaproveitados aqui pra nao
     * duplicar logica; SalesLeadListStats/LeadsByStageChart sao novos,
     * especificos desta listagem (ver docblocks deles).
     */
    protected function getHeaderWidgets(): array
    {
        return [
            SalesLeadListStats::class,
            LeadsByStageChart::class,
            LeadsBySegmentChart::class,
            LeadsBySourceChart::class,
            LeadsCreatedTrendChart::class,
            WonLostTrendChart::class,
            SalesLeadMapWidget::class,
            ProspectingMapWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 2;
    }
}
