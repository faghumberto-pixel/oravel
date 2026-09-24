<?php

namespace App\Filament\Central\Resources\ContratoResource\Pages;

use App\Filament\Central\Resources\ContratoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContratos extends ListRecords
{
    protected static string $resource = ContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Novo Contrato'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ContratoResource\Widgets\ContratoFunnelStats::class,
        ];
    }
}
