<?php

namespace App\Filament\Resources\CrmPropostaResource\Pages;

use App\Filament\Resources\CrmPropostaResource;
use App\Filament\Resources\CrmPropostaResource\Widgets\CrmPropostaStats;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCrmPropostas extends ListRecords
{
    protected static string $resource = CrmPropostaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }

    protected function getHeaderWidgets(): array
    {
        return [CrmPropostaStats::class];
    }
}
