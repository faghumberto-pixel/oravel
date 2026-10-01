<?php

namespace App\Support;

use App\Filament\Central\Resources\ContratoResource;
use App\Models\Plan;

/**
 * Módulos contratados de um Contrato (Plan), agrupados pelos menus do sistema,
 * pra exibir no Contrato de Assinatura (PDF e tela de assinatura). `features`
 * é um MAPA (`['tabela_x' => true|false]`); aceita também o formato antigo em
 * lista (`['tabela_x', ...]`). Só entram os módulos ligados.
 */
class ContractModules
{
    /**
     * @return array<string, array<int, string>> grupo => rótulos dos módulos (ordenados)
     */
    public static function grouped(?Plan $plan): array
    {
        $enabled = [];

        foreach ((array) ($plan?->features ?? []) as $key => $value) {
            if (is_int($key)) {
                $enabled[] = (string) $value; // formato antigo (lista)
            } elseif ($value === true || $value === 1 || $value === '1' || $value === 'true') {
                $enabled[] = (string) $key;
            }
        }

        $enabled = array_flip(array_unique($enabled));
        $grouped = [];

        foreach (ContratoResource::groupedFeatureOptions() as $group => $options) {
            foreach ($options as $key => $label) {
                if (isset($enabled[$key])) {
                    $grouped[$group][] = str_replace('Tabela: ', '', $label);
                    unset($enabled[$key]);
                }
            }
        }

        // Módulos ligados que não aparecem em nenhum grupo do menu.
        if ($enabled) {
            $allOptions = Plan::getAvailableFeaturesOptions();
            foreach (array_keys($enabled) as $key) {
                $grouped['Outros'][] = str_replace('Tabela: ', '', $allOptions[$key] ?? $key);
            }
        }

        foreach ($grouped as &$labels) {
            sort($labels);
        }

        ksort($grouped);

        return $grouped;
    }
}
