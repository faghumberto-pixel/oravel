<?php

namespace App\Filament\Resources\FrotaCustoAvulsoResource\Pages;

use App\Filament\Resources\FrotaCustoAvulsoResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageFrotaCustosAvulsos extends ManageRecords
{
    protected static string $resource = FrotaCustoAvulsoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Novo custo')->mutateFormDataUsing(function (array $data) {
            $data['registrado_por'] = auth()->id();

            return $data;
        })];
    }
}
