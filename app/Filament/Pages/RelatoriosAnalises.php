<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Página-âncora do subgrupo "Análises" dentro de "Relatórios" (reestruturação do menu
 * lateral, 2026-09-23). Sem navigationParentItem de propósito -- é ELA que os demais itens do
 * subgrupo apontam via $navigationParentItem = 'Análises'. O Filament só monta o 2º nível
 * se existir um item real com esse rótulo exato e sem pai no mesmo grupo (NavigationManager::
 * ::class, groupBy(getParentItem())); sem essa âncora os itens filhos somem do menu, sem erro
 * nenhum. Mesmo padrão de Almoxarifado.php ("Gestão de Estoque" dentro de "Ativos e Materiais").
 */
class RelatoriosAnalises extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Análises';

    protected static ?string $title = 'Análises';

    protected static string $view = 'filament.pages.anchor-relatorios-analises';

    protected static ?int $navigationSort = 8;

    protected static bool $shouldRegisterNavigation = true;
}
