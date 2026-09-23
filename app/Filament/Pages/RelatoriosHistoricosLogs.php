<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Página-âncora do subgrupo "Históricos & Logs" dentro de "Relatórios" (reestruturação do menu
 * lateral, 2026-09-23). Sem navigationParentItem de propósito -- é ELA que os demais itens do
 * subgrupo apontam via $navigationParentItem = 'Históricos & Logs'. O Filament só monta o 2º nível
 * se existir um item real com esse rótulo exato e sem pai no mesmo grupo (NavigationManager::
 * ::class, groupBy(getParentItem())); sem essa âncora os itens filhos somem do menu, sem erro
 * nenhum. Mesmo padrão de Almoxarifado.php ("Gestão de Estoque" dentro de "Ativos e Materiais").
 */
class RelatoriosHistoricosLogs extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Históricos & Logs';

    protected static ?string $title = 'Históricos & Logs';

    protected static string $view = 'filament.pages.anchor-relatorios-historicos-logs';

    protected static ?int $navigationSort = 9;

    protected static bool $shouldRegisterNavigation = true;
}
