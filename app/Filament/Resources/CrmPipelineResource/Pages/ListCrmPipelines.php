<?php

namespace App\Filament\Resources\CrmPipelineResource\Pages;

use App\Filament\Resources\CrmPipelineResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCrmPipelines extends ListRecords
{
    protected static string $resource = CrmPipelineResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
