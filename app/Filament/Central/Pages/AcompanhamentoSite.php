<?php

namespace App\Filament\Central\Pages;

use App\Filament\Central\Widgets\Acompanhamento\NotasTable;
use App\Filament\Central\Widgets\Acompanhamento\PaginasTable;
use App\Filament\Central\Widgets\Acompanhamento\PendenciasTable;
use Filament\Pages\Page;

/**
 * Acompanhamento da remodelagem do site institucional (so' super admin): mostra a previa do site novo
 * e, abaixo, notas por data, status por pagina e pendencias/decisoes, tudo editavel no proprio painel.
 */
class AcompanhamentoSite extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Site Institucional';

    protected static ?string $navigationLabel = 'Acompanhamento do Site';

    protected static ?string $title = 'Acompanhamento do site novo';

    protected static ?string $slug = 'acompanhamento-site';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.central.pages.acompanhamento-site';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    protected function getFooterWidgets(): array
    {
        return [PendenciasTable::class, PaginasTable::class, NotasTable::class];
    }

    public function getFooterWidgetsColumns(): int|string|array
    {
        return 1;
    }
}
