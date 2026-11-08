<?php

namespace App\Filament\Resources\CkExecutionResource\Pages;

use App\Filament\Resources\CkExecutionResource;
use App\Support\CkExecucaoExport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCkExecutions extends ListRecords
{
    protected static string $resource = CkExecutionResource::class;

    /** Exporta o que esta na tela: respeita filtros, busca e ordenacao atuais. */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportarExcel')->label('Exportar Excel')->icon('heroicon-o-table-cells')->color('gray')
                ->action(function () {
                    $caminho = CkExecucaoExport::xlsx($this->getFilteredSortedTableQuery());

                    return response()->download($caminho, 'checklists-equipamento-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend();
                }),
        ];
    }
}
