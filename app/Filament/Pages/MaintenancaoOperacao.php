<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Página-âncora do subgrupo "Operação" dentro de "Manutenção" (reestruturação do menu
 * lateral, 2026-09-23). Sem navigationParentItem de propósito -- é ELA que os demais itens do
 * subgrupo apontam via $navigationParentItem = 'Operação'. O Filament só monta o 2º nível
 * se existir um item real com esse rótulo exato e sem pai no mesmo grupo (NavigationManager::
 * ::class, groupBy(getParentItem())); sem essa âncora os itens filhos somem do menu, sem erro
 * nenhum. Mesmo padrão de Almoxarifado.php ("Gestão de Estoque" dentro de "Ativos e Materiais").
 */
class MaintenancaoOperacao extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-play-circle';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationLabel = 'Operação';

    protected static ?string $title = 'Operação';

    protected static string $view = 'filament.pages.anchor-maintenancao-operacao';

    protected static ?int $navigationSort = 1;

    protected static bool $shouldRegisterNavigation = true;
}
