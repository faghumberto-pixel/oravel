<?php

namespace App\Filament\Central\Resources\DashboardTemplateResource\Pages;

use App\Filament\Central\Resources\DashboardTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDashboardTemplates extends ListRecords
{
    protected static string $resource = DashboardTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
