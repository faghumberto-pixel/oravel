<?php

namespace App\Filament\Resources\Nr13DocumentResource\Pages;

use App\Filament\Resources\Nr13DocumentResource;
use Filament\Resources\Pages\ManageRecords;

/**
 * Sem getHeaderActions(): ver docblock de ManageNr13InspectionPeriodicities (a mesma
 * duplicação do botão "Criar").
 */
class ManageNr13Documents extends ManageRecords
{
    protected static string $resource = Nr13DocumentResource::class;
}
