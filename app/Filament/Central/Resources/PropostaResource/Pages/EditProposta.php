<?php

namespace App\Filament\Central\Resources\PropostaResource\Pages;

use App\Filament\Central\Resources\PropostaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProposta extends EditRecord
{
    protected static string $resource = PropostaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['price'] = $data['base_price'] ?? 0;

        return $data;
    }
}
