<?php

namespace App\Filament\Resources\PropostaComercialResource\Pages;

use App\Filament\Resources\PropostaComercialResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Sem CreateAction de propósito -- a proposta só nasce pelo wizard mobile
 * do vendedor (App\Livewire\PropostaComercialMobile), não pelo Filament.
 */
class ListPropostaComerciais extends ListRecords
{
    protected static string $resource = PropostaComercialResource::class;
}
