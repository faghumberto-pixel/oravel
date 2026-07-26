<?php

namespace App\Filament\Resources\AbcMatrixResource\Pages;

use App\Filament\Resources\AbcMatrixResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAbcMatrix extends EditRecord
{
    protected static string $resource = AbcMatrixResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
