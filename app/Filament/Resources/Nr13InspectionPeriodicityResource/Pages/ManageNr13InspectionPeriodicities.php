<?php

namespace App\Filament\Resources\Nr13InspectionPeriodicityResource\Pages;

use App\Filament\Resources\Nr13InspectionPeriodicityResource;
use Filament\Resources\Pages\ManageRecords;

/**
 * Sem getHeaderActions(): o Resource já declara CreateAction em table()->headerActions(), e
 * ManageRecords soma os dois automaticamente -- override aqui duplicava o botão "Criar" (bug
 * real, confirmado visualmente e também presente no precedente copiado,
 * RentalHourFranchiseResource/ManageRentalHourFranchises; corrigido só aqui, não lá, por escopo).
 */
class ManageNr13InspectionPeriodicities extends ManageRecords
{
    protected static string $resource = Nr13InspectionPeriodicityResource::class;
}
