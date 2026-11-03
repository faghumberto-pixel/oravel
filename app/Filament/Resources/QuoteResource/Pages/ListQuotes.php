<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Concerns\HasCartoesFiltro;
use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\QuoteExporter;
use App\Filament\Resources\QuoteResource;
use App\Models\Quote;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListQuotes extends ListRecords
{
    use HasCartoesFiltro;
    use HasPrintAction;

    protected static string $resource = QuoteResource::class;

    protected static string $view = 'filament.resources.lista-com-cartoes';

    /** Cards de totais clicaveis: um por situacao do orcamento. */
    protected function definicaoCartoes(): array
    {
        $cards = ['total' => ['Total', [], 'gray']];
        foreach (QuoteResource::COR_STATUS as $status => $cor) {
            $cards[$status] = [Quote::statusLabels()[$status] ?? $status, ['status' => $status], $cor];
        }

        foreach ([Quote::TYPE_INTERNO => 'cyan', Quote::TYPE_TERCEIRO => 'teal', Quote::TYPE_INDENIZATORIO => 'pink'] as $tipo => $cor) {
            $cards['tipo_'.$tipo] = [Quote::typeLabels()[$tipo] ?? $tipo, ['type' => $tipo], $cor];
        }

        return $cards;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->printAction(),
            Actions\ExportAction::make()->exporter(QuoteExporter::class),
        ];
    }

    /** Sem widgets no topo (pedido do usuario 2026-11-03): so' os cards de totais clicaveis acima da lista. */
    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
