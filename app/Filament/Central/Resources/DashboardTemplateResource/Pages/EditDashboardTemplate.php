<?php

namespace App\Filament\Central\Resources\DashboardTemplateResource\Pages;

use App\Filament\Central\Resources\DashboardTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDashboardTemplate extends EditRecord
{
    protected static string $resource = DashboardTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
