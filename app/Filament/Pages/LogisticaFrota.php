<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Página-âncora do subgrupo "Frota" dentro de "Logística" (reestruturação do menu
 * lateral, 2026-09-23). Sem navigationParentItem de propósito -- é ELA que os demais itens do
 * subgrupo apontam via $navigationParentItem = 'Frota'. O Filament só monta o 2º nível
 * se existir um item real com esse rótulo exato e sem pai no mesmo grupo (NavigationManager::
 * ::class, groupBy(getParentItem())); sem essa âncora os itens filhos somem do menu, sem erro
 * nenhum. Mesmo padrão de Almoxarifado.php ("Gestão de Estoque" dentro de "Ativos e Materiais").
 */
class LogisticaFrota extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationLabel = 'Frota';

    protected static ?string $title = 'Frota';

    protected static string $view = 'filament.pages.anchor-logistica-frota';

    protected static ?int $navigationSort = 1;

    protected static bool $shouldRegisterNavigation = true;
}
