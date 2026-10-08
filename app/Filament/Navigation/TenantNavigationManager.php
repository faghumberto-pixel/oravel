<?php

namespace App\Filament\Navigation;

use Filament\Navigation\NavigationManager;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Support\SaaSRegistry;

class TenantNavigationManager extends NavigationManager
{
    public function getNavigation(): array
    {
        $navigationTree = parent::getNavigation();

        $tenant = \App\Support\Tenancy::current();
        if (! $tenant) {
            return $navigationTree;
        }

        $tenant->loadMissing('plan');
        $featuresOriginal = $tenant->plan->features ?? [];

        // Higienizador contra tipagem inconsistente do banco.
        $featuresPermitidas = [];
        foreach ($featuresOriginal as $chave => $valor) {
            if (is_string($chave)) {
                if ($valor === true || $valor === 1 || $valor === '1' || $valor === 'true') {
                    $featuresPermitidas[] = $chave;
                }
            } else {
                if ($valor !== false && $valor !== 0 && $valor !== '0' && $valor !== 'false') {
                    $featuresPermitidas[] = $valor;
                }
            }
        }

        $user = Auth::user();
        $isAdmin = $user && method_exists($user, 'isAdmin') && $user->isAdmin();

        // Mapa URL-slug => metadados, construído automaticamente a partir do registry.
        // Para cada módulo, derivamos o segmento de URL do Resource (kebab do nome do model).
        $regras = $this->montarRegras();

        $menusPai = $this->rotulosDeMenuPai();
        $paginasBloqueadas = $this->paginasBloqueadas();

        $arvoreFiltrada = [];

        foreach ($navigationTree as $elemento) {
            if ($elemento instanceof NavigationGroup) {
                $itensFiltrados = [];
                foreach ($elemento->getItems() as $item) {
                    $this->filtrarFilhos($item, $paginasBloqueadas);

                    if (! isset($paginasBloqueadas[$item->getUrl()])
                        && $this->permitidoAcessar($item, $regras, $featuresPermitidas, $user, $isAdmin)
                        && ! $this->menuPaiVazio($item, $menusPai)) {
                        $itensFiltrados[] = $item;
                    }
                }
                if (! empty($itensFiltrados)) {
                    $arvoreFiltrada[] = $elemento->items($itensFiltrados);
                }
            } elseif ($elemento instanceof NavigationItem) {
                if ($this->permitidoAcessar($elemento, $regras, $featuresPermitidas, $user, $isAdmin)) {
                    $arvoreFiltrada[] = $elemento;
                }
            } else {
                $arvoreFiltrada[] = $elemento;
            }
        }

        return $arvoreFiltrada;
    }

    /**
     * Rótulos de "menu pai" (Relatórios, Análises, Painéis, Históricos & Logs,
     * Gestão Comercial...): páginas-índice que só existem para agrupar outros
     * itens via navigationParentItem. Não pertencem a nenhum módulo do plano,
     * então o filtro por URL as deixava sempre visíveis, mesmo quando todos
     * os filhos estavam bloqueados pelo contrato ou pelas permissões.
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
     * O Filament só usa canAccess() de uma Page para bloquear a ROTA; o item
     * continua aparecendo no menu. Por isso Fluxo de Caixa, Conciliação
     * Bancária e outras páginas fora do plano do cliente apareciam mesmo
     * dando "acesso negado" ao clicar. Aqui o menu passa a respeitar o mesmo
     * canAccess() que protege a rota.
     *
     * @return array<string, true> url => true
     */
    protected function paginasBloqueadas(): array
    {
        $bloqueadas = [];
        $painel = Filament::getCurrentPanel();

        foreach ($painel?->getPages() ?? [] as $pagina) {
            try {
                if (! $pagina::canAccess()) {
                    $bloqueadas[$pagina::getUrl()] = true;
                }
            } catch (\Throwable) {
                // Página que precisa de parâmetro de rota para gerar a URL: não entra no menu.
            }
        }

        return $bloqueadas;
    }

    /**
     * Remove dos filhos de um menu pai as páginas bloqueadas por canAccess().
     *
     * @param  array<string, true>  $paginasBloqueadas
     */
    protected function filtrarFilhos(NavigationItem $item, array $paginasBloqueadas): void
    {
        $filhos = collect($item->getChildItems())
            ->reject(fn (NavigationItem $filho) => isset($paginasBloqueadas[$filho->getUrl()]))
            ->values()
            ->all();

        $item->childItems($filhos);
    }

    /**
     * Menu pai sem nenhum filho visível some do menu.
     *
     * @param  array<string, true>  $menusPai
     */
    protected function menuPaiVazio(NavigationItem $item, array $menusPai): bool
    {
        return isset($menusPai[$item->getLabel()]) && blank($item->getChildItems());
    }

    /**
     * Constrói as regras de visibilidade a partir do SaaSRegistry.
     * Cada módulo gera: segmento de URL => [feature, model].
     */
    protected function montarRegras(): array
    {
        $regras = [];
        foreach (SaaSRegistry::modules() as $m) {
            // Segmento de URL que o Filament usa: kebab-case do nome curto do Model, pluralizado.
            $base = \Illuminate\Support\Str::kebab(class_basename($m['model']));
            $plural = \Illuminate\Support\Str::plural($base);

            $regras[$plural] = ['feature' => $m['feature'], 'model' => $m['model']];
            $regras[$base]   = ['feature' => $m['feature'], 'model' => $m['model']];
        }
        return $regras;
    }

    protected function permitidoAcessar(
        NavigationItem $item,
        array $regras,
        array $featuresPermitidas,
        $user,
        bool $isAdmin
    ): bool {
        $url = strtolower($item->getUrl());

        // Ordena por tamanho do segmento (mais específico primeiro) para evitar
        // colisão de substring (ex.: material-categories antes de materials).
        $segmentos = array_keys($regras);
        usort($segmentos, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($segmentos as $segmento) {
            if (str_contains($url, $segmento)) {
                $regra = $regras[$segmento];

                // 1) Trava comercial (plano).
                $featureKey = $regra['feature'] ?? null;
                if ($featureKey && ! in_array($featureKey, $featuresPermitidas, true)) {
                    return false;
                }

                // 2) Admin do tenant vê tudo que passou no plano.
                if ($isAdmin) {
                    return true;
                }

                // 3) Permissão individual via Gate (usa AbstractPolicy + registry).
                if (isset($regra['model']) && $user) {
                    return Gate::forUser($user)->check('viewAny', $regra['model']);
                }

                return false;
            }
        }

        return true; // Menu não mapeado (ex.: chat, dashboard): exibe por padrão.
    }
}