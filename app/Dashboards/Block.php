<?php

namespace App\Dashboards;

use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;

/**
 * Um bloco do catálogo de dashboards (chave única, módulo exigido, tamanho na grade de 12 colunas e a conta
 * que busca os dados). `data()` devolve um "payload" de um dos tipos aceitos por resources/views/dashboards/types:
 * kpi, bars, cols, line, funnel, table, alerts.
 *
 * Os dados saem dos models do tenant, então o isolamento vem do global scope de BelongsToTenant. Quem decide se o
 * bloco aparece é visibleTo(): módulo contratado + permissão de leitura do recurso de origem.
 */
abstract class Block
{
    /** Tamanhos permitidos na grade de 12 colunas (os mesmos do exemplo aprovado). */
    public const SIZES = [2, 3, 4, 6, 8, 12];

    abstract public static function key(): string;

    abstract public static function title(): string;

    abstract public static function subtitle(): string;

    /** Grupo do contrato a que o bloco pertence (ContratoResource::groupedFeatureOptions). */
    abstract public static function module(): string;

    /** Chave do módulo no contrato (SaaSRegistry), ex.: tabela_maintenance_orders. */
    abstract public static function requiredFeature(): string;

    /** Model do recurso de origem, para a checagem de permissão de leitura (policy viewAny). */
    abstract public static function sourceModel(): string;

    public static function size(): int
    {
        return 4;
    }

    /** Limites, filtros e período que a personalização por empresa poderá ajustar. */
    public static function defaultConfig(): array
    {
        return [];
    }

    abstract public function data(array $config): array;

    /**
     * Junta a configuração padrão com os ajustes de um dashboard personalizado. Só vale chave que o bloco declara em
     * defaultConfig(), e o valor é convertido para o tipo do padrão (número continua número).
     *
     * @param  array<string, mixed>|null  $ajustes
     */
    public static function mergeConfig(?array $ajustes): array
    {
        $padrao = static::defaultConfig();

        foreach ($padrao as $chave => $valorPadrao) {
            if (! is_array($ajustes) || ! array_key_exists($chave, $ajustes) || $ajustes[$chave] === '' || $ajustes[$chave] === null) {
                continue;
            }

            $valor = $ajustes[$chave];
            if (is_int($valorPadrao) && is_numeric($valor)) {
                $padrao[$chave] = (int) $valor;
            } elseif (is_float($valorPadrao) && is_numeric($valor)) {
                $padrao[$chave] = (float) $valor;
            } elseif (is_string($valorPadrao)) {
                $padrao[$chave] = (string) $valor;
            }
        }

        return $padrao;
    }

    public static function visibleTo(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $tenant = Tenancy::current();
        if (! $tenant || ! $tenant->hasFeature(static::requiredFeature())) {
            return false;
        }

        return Gate::forUser($user)->allows('viewAny', static::sourceModel());
    }
}
