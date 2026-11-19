<?php

namespace App\Relacionamento\Filament\Resources\RelAcaoRegraResource\Pages;

use App\Relacionamento\Filament\Resources\RelAcaoRegraResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageRelAcaoRegras extends ManageRecords
{
    protected static string $resource = RelAcaoRegraResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nova regra')];
    }
}
