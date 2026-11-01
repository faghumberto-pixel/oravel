<?php

namespace App\Filament\Central\Resources\AcompanhamentoResource\Pages;

use App\Filament\Central\Resources\AcompanhamentoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAcompanhamento extends ViewRecord
{
    protected static string $resource = AcompanhamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }
}
