<?php

namespace App\Filament\Navigation;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use App\Support\MenuModules;
use App\Support\Tenancy;
use Filament\Navigation\NavigationManager;

/**
 * Menu lateral do painel admin (registrado em AppServiceProvider no lugar do
 * NavigationManager do Filament).
 *
 * Duas regras que o Filament não aplica sozinho:
 *  1. O canAccess() de uma Page só bloqueia a ROTA; o item continuava no
 *     menu mesmo fora do plano do cliente (ex.: Fluxo de Caixa e Conciliação
 *     Bancária) e dava "acesso negado" ao clicar.
 *  2. Menus pai (Relatórios, Análises, Painéis, Históricos & Logs, Gestão
 *     Comercial...) são páginas-índice sem módulo próprio. Ficavam sempre
 *     visíveis, mesmo sem nenhum item liberado dentro deles.
 *
 * Recursos continuam filtrados pelas policies (plano + permissão), como antes.
 */
class TenantNavigationManager extends NavigationManager
{
    public function get(): array
    {
        $grupos = parent::get();

        $bloqueadas = $this->paginasBloqueadas();
        $menusPai = $this->rotulosDeMenuPai();

        $resultado = [];

        foreach ($grupos as $grupo) {
            if (! $grupo instanceof NavigationGroup) {
                $resultado[] = $grupo;

                continue;
            }

            $itens = [];

            foreach ($grupo->getItems() as $item) {
                $this->removerFilhosBloqueados($item, $bloqueadas);

                if (! $this->menuLiberadoNoContrato($grupo->getLabel(), $item)) {
                    continue;
                }

                if (isset($bloqueadas[$item->getUrl()])) {
                    continue;
                }

                if (isset($menusPai[$item->getLabel()]) && blank($item->getChildItems())) {
                    continue;
                }

                $itens[] = $item;
            }

            if ($itens !== []) {
                $resultado[] = $grupo->items($itens);
            }
        }

        return $resultado;
    }

    /**
     * Menus pai e páginas com módulo próprio (config/menu_modules.php) só
     * aparecem quando o contrato do cliente libera a chave. Sem cliente
     * atuante (super admin na Central) ou para super admin, tudo aparece.
     */
    protected function menuLiberadoNoContrato(?string $grupo, NavigationItem $item): bool
    {
        $chave = MenuModules::keyForItem($grupo, $item->getLabel());
        $tenant = Tenancy::current();

        if (! $chave || ! $tenant) {
            return true;
        }

        return $tenant->hasFeature($chave);
    }

    /**
     * @return array<string, true> url => true
     */
    protected function paginasBloqueadas(): array
    {
        $bloqueadas = [];

        foreach (Filament::getCurrentPanel()?->getPages() ?? [] as $pagina) {
            try {
                if (! $pagina::canAccess()) {
                    $bloqueadas[$pagina::getUrl()] = true;
                }
            } catch (\Throwable) {
                // Página que precisa de parâmetro de rota para gerar a URL: fora desta regra.
            }
        }

        return $bloqueadas;
    }

    /**
     * Rótulos usados como navigationParentItem por algum Resource ou Page.
     *
     * @return array<string, true>
     */
    protected function rotulosDeMenuPai(): array
    {
        $rotulos = [];
        $painel = Filament::getCurrentPanel();

        foreach (array_merge($painel?->getResources() ?? [], $painel?->getPages() ?? []) as $classe) {
            if (method_exists($classe, 'getNavigationParentItem') && $pai = $classe::getNavigationParentItem()) {
                $rotulos[$pai] = true;
            }
        }

        return $rotulos;
    }

    /**
     * @param  array<string, true>  $bloqueadas
     */
    protected function removerFilhosBloqueados(NavigationItem $item, array $bloqueadas): void
    {
        $filhos = collect($item->getChildItems())
            ->reject(fn (NavigationItem $filho) => isset($bloqueadas[$filho->getUrl()]))
            ->values()
            ->all();

        $item->childItems($filhos);
    }
}
