<?php

namespace App\Filament\Resources\MaterialResource\Pages;

use App\Filament\Concerns\HasCartoesFiltro;
use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\MaterialExporter;
use App\Filament\Resources\MaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterials extends ListRecords
{
    use HasCartoesFiltro;
    use HasPrintAction;

    protected static string $resource = MaterialResource::class;

    protected static string $view = 'filament.resources.lista-com-cartoes';

    /** Cards de totais clicaveis: total, abaixo do minimo e situacao do fornecedor. */
    protected function definicaoCartoes(): array
    {
        return [
            'total' => ['Total', [], 'gray'],
            'baixo' => ['Abaixo do estoque mínimo', ['estoque_baixo' => true], 'red'],
            'sem_fornecedor' => ['Sem fornecedor', ['supplier_id' => '0'], 'amber'],
            'com_fornecedor' => ['Com fornecedor', ['supplier_id' => '1'], 'emerald'],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->printAction(),
            Actions\ExportAction::make()->exporter(MaterialExporter::class),
        ];
    }

    /** Sem widgets/graficos no topo (pedido do usuario 2026-11-03): so' os cards de totais clicaveis. */
    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
