<?php

namespace App\Filament\Resources\AbsenceResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Resources\AbsenceResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAbsences extends ManageRecords
{
    use HasPrintAction;

    protected static string $resource = AbsenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->printAction(),
        ];
    }
}
