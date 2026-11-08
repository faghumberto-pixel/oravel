<?php

namespace App\Filament\Pages;

use App\Services\Catalogo\PecasCriticas;
use App\Support\CatalogoAcesso;
use App\Support\Tenancy;
use Filament\Pages\Page;

/** G1 fase 7 -- pecas criticas dos grupos de equipamento e o estoque de cada uma (Material), da mais grave para a mais leve. */
class PecasCriticasGrupos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationParentItem = 'Cadastros';

    protected static ?string $navigationLabel = 'Peças críticas';

    protected static ?string $title = 'Peças críticas dos grupos';

    protected static ?string $slug = 'catalogo-pecas-criticas';

    protected static ?int $navigationSort = 8;

    protected static string $view = 'filament.pages.pecas-criticas-grupos';

    public static function canAccess(): bool
    {
        return CatalogoAcesso::podeLer();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return CatalogoAcesso::podeLer();
    }

    public function getLinhasProperty(): array
    {
        $tenant = Tenancy::current();

        return $tenant ? PecasCriticas::situacao((string) $tenant->id)->all() : [];
    }
}
