<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Concerns\HasCartoesFiltro;
use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\ContractExporter;
use App\Filament\Resources\ContractResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContracts extends ListRecords
{
    use HasCartoesFiltro;
    use HasPrintAction;

    protected static string $resource = ContractResource::class;

    protected static string $view = 'filament.resources.lista-com-cartoes';

    /** Cards de totais clicaveis: situacao, vigencia e assinatura (cor por significado). */
    protected function definicaoCartoes(): array
    {
        return [
            'total' => ['Total', [], 'gray'],
            'rascunho' => ['Rascunho', ['status' => 'Draft'], 'cyan'],
            'ativo' => ['Ativos', ['status' => 'Ativo'], 'emerald'],
            'encerrado' => ['Encerrados', ['status' => 'Encerrado'], 'purple'],
            'vence30' => ['Vencem em 30 dias', ['vence_em_30_dias' => true], 'amber'],
            'vencida' => ['Vigência vencida', ['vigencia_vencida' => true], 'red'],
            'assinados' => ['Assinados', ['assinatura' => 'assinado'], 'blue'],
            'pendentes' => ['Assinatura pendente', ['assinatura' => 'pendente'], 'orange'],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->printAction(),
            Actions\ExportAction::make()->exporter(ContractExporter::class),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ContractResource\Widgets\ContractStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
