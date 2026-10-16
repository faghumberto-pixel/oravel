<?php

namespace App\Filament\Resources\CrmCampoCustomResource\Pages;

use App\Filament\Resources\CrmCampoCustomResource;
use App\Support\CrmCamposCustom;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCrmCampoCustoms extends ManageRecords
{
    protected static string $resource = CrmCampoCustomResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->after(fn () => CrmCamposCustom::esquecer())];
    }
}
