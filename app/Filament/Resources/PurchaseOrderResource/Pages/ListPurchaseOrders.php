<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Concerns\HasCartoesFiltro;
use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\PurchaseOrderExporter;
use App\Filament\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseOrders extends ListRecords
{
    use HasCartoesFiltro;
    use HasPrintAction;

    protected static string $resource = PurchaseOrderResource::class;

    protected static string $view = 'filament.resources.lista-com-cartoes';

    /** Cards de totais clicaveis: situacao da ordem e entrega atrasada. */
    protected function definicaoCartoes(): array
    {
        return [
            'total' => ['Total', [], 'gray'],
            'aberta' => ['Abertas', ['status' => PurchaseOrder::STATUS_ABERTA], 'blue'],
            'parcial' => ['Parcialmente recebidas', ['status' => PurchaseOrder::STATUS_PARCIALMENTE_RECEBIDA], 'amber'],
            'recebida' => ['Recebidas', ['status' => PurchaseOrder::STATUS_RECEBIDA], 'emerald'],
            'cancelada' => ['Canceladas', ['status' => PurchaseOrder::STATUS_CANCELADA], 'gray'],
            'atrasada' => ['Entrega atrasada', ['entrega_atrasada' => true], 'red'],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nova Ordem de Compra (Avulsa)'),
            $this->printAction(),
            Actions\ExportAction::make()->exporter(PurchaseOrderExporter::class),
        ];
    }

    /** Sem widgets no topo (pedido do usuario 2026-11-03): so' os cards de totais clicaveis. */
    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
