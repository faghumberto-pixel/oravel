<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Página-âncora do subgrupo "Cadastros" dentro de "Manutenção" (reestruturação do menu
 * lateral, 2026-09-23). Sem navigationParentItem de propósito -- é ELA que os demais itens do
 * subgrupo apontam via $navigationParentItem = 'Cadastros'. O Filament só monta o 2º nível
 * se existir um item real com esse rótulo exato e sem pai no mesmo grupo (NavigationManager::
 * ::class, groupBy(getParentItem())); sem essa âncora os itens filhos somem do menu, sem erro
 * nenhum. Mesmo padrão de Almoxarifado.php ("Gestão de Estoque" dentro de "Ativos e Materiais").
 */
class MaintenancaoCadastros extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationLabel = 'Cadastros';

    protected static ?string $title = 'Cadastros';

    protected static string $view = 'filament.pages.anchor-maintenancao-cadastros';

    protected static ?int $navigationSort = 9;

    protected static bool $shouldRegisterNavigation = true;
}
