<?php

namespace App\Filament\Resources\StockLocationResource\Pages;

use App\Filament\Resources\StockLocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStockLocation extends EditRecord
{
    protected static string $resource = StockLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('etiqueta')->label('Etiqueta QR deste endereço')->icon('heroicon-o-qr-code')->color('gray')
                ->url(fn () => route('estoque.etiquetas', ['tipo' => 'enderecos', 'modelo' => 'grande', 'ids' => [$this->record->id]]))->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }
}
