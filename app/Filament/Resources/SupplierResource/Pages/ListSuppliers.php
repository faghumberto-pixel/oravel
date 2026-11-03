<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Concerns\HasCartoesFiltro;
use App\Filament\Concerns\HasPrintAction;
use App\Filament\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    use HasCartoesFiltro;
    use HasPrintAction;

    protected static string $resource = SupplierResource::class;

    protected static string $view = 'filament.resources.lista-com-cartoes';

    /** Cards de totais clicaveis: compliance, certidoes e termo LGPD. */
    protected function definicaoCartoes(): array
    {
        return [
            'total' => ['Total', [], 'gray'],
            'compliance' => ['Compliance completo', ['compliance_completo' => true], 'emerald'],
            'certidoes' => ['Certidões pendentes', ['certidoes_pendentes' => true], 'amber'],
            'sem_lgpd' => ['Sem termo LGPD', ['termo_lgpd' => '0'], 'red'],
            'com_lgpd' => ['Com termo LGPD', ['termo_lgpd' => '1'], 'blue'],
        ];
    }

    protected static ?string $title = 'Fornecedores';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->printAction(),
        ];
    }

    /** Sem widgets/graficos no topo (pedido do usuario 2026-11-03): so' os cards de totais clicaveis. */
    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
