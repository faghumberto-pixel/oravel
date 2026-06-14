<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Resources\AssetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Facades\Filament;

class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('printQr')
                ->label('Imprimir Etiqueta')
                ->icon('heroicon-o-printer')
                ->color('info')
                // A correção está aqui: enviando o tenant e o asset para a rota
                ->url(fn ($record): string => route('asset.print-qr', [
                    'tenant' => \App\Support\Tenancy::current()?->slug ?? $record->tenant_id,
                    'asset'  => $record->id
                ]))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }
}