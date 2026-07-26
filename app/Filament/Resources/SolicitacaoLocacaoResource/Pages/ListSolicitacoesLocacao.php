<?php

namespace App\Filament\Resources\SolicitacaoLocacaoResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Resources\SolicitacaoLocacaoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSolicitacoesLocacao extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = SolicitacaoLocacaoResource::class;

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
            SolicitacaoLocacaoResource\Widgets\SolicitacaoLocacaoStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
