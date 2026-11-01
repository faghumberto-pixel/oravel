<?php

namespace App\Filament\Central\Resources\AcompanhamentoResource\Pages;

use App\Filament\Central\Resources\AcompanhamentoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAcompanhamento extends EditRecord
{
    protected static string $resource = AcompanhamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()->label('Abrir'), Actions\DeleteAction::make()];
    }
}
