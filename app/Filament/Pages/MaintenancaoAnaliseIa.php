<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Página-âncora do subgrupo "Análise (IA)" dentro de "Manutenção" (reestruturação do menu
 * lateral, 2026-09-23). Sem navigationParentItem de propósito -- é ELA que os demais itens do
 * subgrupo apontam via $navigationParentItem = 'Análise (IA)'. O Filament só monta o 2º nível
 * se existir um item real com esse rótulo exato e sem pai no mesmo grupo (NavigationManager::
 * ::class, groupBy(getParentItem())); sem essa âncora os itens filhos somem do menu, sem erro
 * nenhum. Mesmo padrão de Almoxarifado.php ("Gestão de Estoque" dentro de "Ativos e Materiais").
 */
class MaintenancaoAnaliseIa extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationLabel = 'Análise (IA)';

    protected static ?string $title = 'Análise (IA)';

    protected static string $view = 'filament.pages.anchor-maintenancao-analise-ia';

    protected static ?int $navigationSort = 8;

    protected static bool $shouldRegisterNavigation = true;
}
