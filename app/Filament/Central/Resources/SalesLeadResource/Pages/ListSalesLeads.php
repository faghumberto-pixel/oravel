<?php

namespace App\Filament\Central\Resources\SalesLeadResource\Pages;

use App\Filament\Central\Resources\SalesLeadResource;
use App\Filament\Central\Resources\SalesLeadResource\Widgets\InteractionChannelChart;
use App\Filament\Central\Resources\SalesLeadResource\Widgets\InteractionChannelStats;
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
     * 10 widgets acima do grid (pedido do usuario 2026-08-10: cards e
     * graficos como o padrao do app -- ver ListAssets -- no minimo 8).
     * Os 2 mapas ja existem no Dashboard CRM, reaproveitados aqui pra nao
     * duplicar logica; os demais sao novos, especificos desta listagem
     * (ver docblocks deles). InteractionChannelStats/Chart respondem
     * "quantos e quais leads eu contatei por canal" (pedido 2026-08-10).
     *
     * Somem quando algum filtro esta ativo (pedido do usuario 2026-08-10:
     * "quando clico em um card deve aparecer a lista sem nenhum card ou
     * grafico") -- os cards sao a porta de entrada pra uma pergunta ("quem
     * eu contatei por email?"), a resposta e' so' a lista, sem repetir os
     * mesmos 10 widgets em cima dela.
     */
    protected function getHeaderWidgets(): array
    {
        if ($this->hasActiveTableFilters()) {
            return [];
        }

        return [
            SalesLeadListStats::class,
            InteractionChannelStats::class,
            InteractionChannelChart::class,
            LeadsByStageChart::class,
            LeadsBySegmentChart::class,
            LeadsBySourceChart::class,
            LeadsCreatedTrendChart::class,
            WonLostTrendChart::class,
            SalesLeadMapWidget::class,
            ProspectingMapWidget::class,
        ];
    }

    private function hasActiveTableFilters(): bool
    {
        foreach ($this->tableFilters ?? [] as $filterData) {
            foreach ((array) $filterData as $value) {
                if (filled($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 2;
    }
}
