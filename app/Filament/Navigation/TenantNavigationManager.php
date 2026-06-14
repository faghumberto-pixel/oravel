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

        $arvoreFiltrada = [];

        foreach ($navigationTree as $elemento) {
            if ($elemento instanceof NavigationGroup) {
                $itensFiltrados = [];
                foreach ($elemento->getItems() as $item) {
                    if ($this->permitidoAcessar($item, $regras, $featuresPermitidas, $user, $isAdmin)) {
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