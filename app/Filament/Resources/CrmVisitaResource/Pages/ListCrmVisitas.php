<?php

namespace App\Filament\Resources\CrmVisitaResource\Pages;

use App\Filament\Resources\CrmVisitaResource;
use App\Filament\Resources\CrmVisitaResource\Widgets\CrmVisitaStats;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCrmVisitas extends ListRecords
{
    protected static string $resource = CrmVisitaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }

    protected function getHeaderWidgets(): array
    {
        return [CrmVisitaStats::class];
    }
}
