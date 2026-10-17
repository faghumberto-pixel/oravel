<?php

namespace App\Filament\Central\Resources\TaxaConfiguracaoResource\Pages;

use App\Filament\Central\Resources\TaxaConfiguracaoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTaxasConfiguracao extends ListRecords
{
    protected static string $resource = TaxaConfiguracaoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nova taxa')];
    }
}
