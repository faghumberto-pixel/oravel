<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MaterialResource;
use App\Models\StorageLocation;
use Filament\Pages\Page;

/**
 * Localizar peca fisicamente no almoxarifado -- consome o mesmo componente
 * (App\Livewire\PlantaBaixaGrid) usado por PlantaBaixaPatioAtivos, so' que
 * com context=almoxarifado.
 */
class PlantaBaixaAlmoxarifado extends Page
{
    // Navegacao manual dentro do submenu "Almoxarifado" (AdminPanelProvider),
    // nao auto-registrada -- mesmo padrao de MaterialRequestResource etc.
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationLabel = 'Planta Baixa (Almoxarifado)';

    protected static ?string $title = 'Planta Baixa — Almoxarifado';

    protected static ?string $slug = 'planta-baixa-almoxarifado';

    protected static string $view = 'filament.pages.planta-baixa-almoxarifado';

    public static function canAccess(): bool
    {
        return MaterialResource::canViewAny();
    }

    public function getContext(): string
    {
        return StorageLocation::CONTEXT_ALMOXARIFADO;
    }
}
