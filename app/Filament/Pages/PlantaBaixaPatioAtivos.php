<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AssetResource;
use App\Models\StorageLocation;
use Filament\Pages\Page;

/**
 * Localizar equipamento no patio, por unidade (matriz/filial) -- mesmo
 * componente (App\Livewire\PlantaBaixaGrid) do almoxarifado, so' que com
 * context=patio_ativos (habilita filtros de status/capacidade/patrimonio
 * e o toggle de cor por criticidade).
 */
class PlantaBaixaPatioAtivos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationLabel = 'Planta Baixa (Pátio de Ativos)';

    protected static ?string $title = 'Planta Baixa — Pátio de Ativos';

    protected static ?string $slug = 'planta-baixa-patio-ativos';

    protected static string $view = 'filament.pages.planta-baixa-patio-ativos';

    public static function canAccess(): bool
    {
        return AssetResource::canViewAny();
    }

    public function getContext(): string
    {
        return StorageLocation::CONTEXT_PATIO_ATIVOS;
    }
}
