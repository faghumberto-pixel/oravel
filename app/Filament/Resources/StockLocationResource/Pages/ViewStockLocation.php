<?php

namespace App\Filament\Resources\StockLocationResource\Pages;

use App\Filament\Resources\StockLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

/** Consulta de um endereço (aberta pelo QR da prateleira): somente leitura, sem Excluir; editar fica num botão à parte. */
class ViewStockLocation extends ViewRecord
{
    protected static string $resource = StockLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('Editar endereço')->color('gray'),
        ];
    }
}
