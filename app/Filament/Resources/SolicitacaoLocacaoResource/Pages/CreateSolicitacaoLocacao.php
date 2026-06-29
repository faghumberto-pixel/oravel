<?php

namespace App\Filament\Resources\SolicitacaoLocacaoResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\SolicitacaoLocacaoResource;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('rental_requests')]
class CreateSolicitacaoLocacao extends CreateRecord
{
    protected static string $resource = SolicitacaoLocacaoResource::class;
}