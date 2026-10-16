<?php

namespace App\Filament\Resources\CrmDespesaResource\Pages;

use App\Filament\Resources\CrmDespesaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCrmDespesa extends CreateRecord
{
    protected static string $resource = CrmDespesaResource::class;

    /** A despesa é sempre do usuário que lança (o admin decide depois). */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['status'] = 'pendente';

        return $data;
    }
}
