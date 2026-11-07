<?php

namespace App\Filament\Resources\PriorityLevelResource\Pages;

use App\Filament\Resources\PriorityLevelResource;
use App\Services\Prioridade\PrioridadeDaOs;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPriorityLevels extends ListRecords
{
    protected static string $resource = PriorityLevelResource::class;

    public function mount(): void
    {
        if ($tenantId = Tenancy::current()?->id) {
            PrioridadeDaOs::garantirPadrao((string) $tenantId);
        }

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
