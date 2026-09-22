<?php

namespace App\Filament\Resources\Nr13InspectionResource\Pages;

use App\Filament\Resources\Nr13InspectionResource;
use Filament\Resources\Pages\ManageRecords;

/**
 * Sem getHeaderActions(): ver docblock de ManageNr13InspectionPeriodicities (a mesma
 * duplicação do botão "Criar").
 */
class ManageNr13Inspections extends ManageRecords
{
    protected static string $resource = Nr13InspectionResource::class;
}
