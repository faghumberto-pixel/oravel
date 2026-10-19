<?php

namespace App\Filament\Resources\ReportedProblemResource\Pages;

use App\Filament\Resources\ReportedProblemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReportedProblems extends ListRecords
{
    protected static string $resource = ReportedProblemResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Novo problema comum')];
    }
}
