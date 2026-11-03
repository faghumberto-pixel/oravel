<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Pages;

use App\Filament\Concerns\HasCartoesFiltro;
use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\MaintenanceOrderExporter;
use App\Filament\Resources\MaintenanceOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceOrders extends ListRecords
{
    use HasCartoesFiltro;
    use HasPrintAction;

    protected static string $resource = MaintenanceOrderResource::class;

    protected static string $view = 'filament.resources.lista-com-cartoes';

    /** Cards de totais clicaveis: situacao da OS, atraso, retrabalho, PMP automatica e tipo. */
    protected function definicaoCartoes(): array
    {
        return [
            'total' => ['Total', [], 'gray'],
            'aberto' => ['Abertas', ['status' => ['Aberto']], 'blue'],
            'pendente' => ['Pendentes', ['status' => ['Pendente']], 'amber'],
            'andamento' => ['Em andamento', ['status' => ['Em Andamento']], 'indigo'],
            'concluida' => ['Concluídas', ['status' => ['Concluída']], 'emerald'],
            'cancelada' => ['Canceladas', ['status' => ['Cancelada']], 'gray'],
            'atrasada' => ['Em atraso (+3 dias)', ['atrasada' => true], 'red'],
            'retrabalho' => ['Retrabalho', ['is_rework' => true], 'orange'],
            'pmp' => ['PMP automática', ['origin_pmp_auto' => true], 'cyan'],
            'preventiva' => ['Preventivas', ['maintenance_type' => 'Preventiva'], 'teal'],
            'corretiva' => ['Corretivas', ['maintenance_type' => 'Corretiva'], 'pink'],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // ADIÇÃO DO BOTÃO CRIAR NO TOPO
            Actions\CreateAction::make()
                ->label('Nova Ordem de Serviço')
                ->icon('heroicon-m-plus'),
            $this->printAction(),
            Actions\ExportAction::make()->exporter(MaintenanceOrderExporter::class),
        ];
    }

    /** Sem widgets no topo (pedido do usuario 2026-11-03): so' os cards de totais clicaveis acima da lista. */
    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
