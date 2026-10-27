<?php

namespace App\Dashboards;

/** Construtores dos 7 tipos de payload que as views em resources/views/dashboards/types sabem desenhar. */
class Payload
{
    /** @param 'up'|'dn'|'n' $tone up = verde, dn = vermelho, n = cinza */
    public static function kpi(string|int|float $value, string $unit = '', string $delta = '', string $tone = 'n', ?array $bar = null): array
    {
        return ['type' => 'kpi', 'value' => $value, 'unit' => $unit, 'delta' => $delta, 'tone' => $tone, 'bar' => $bar];
    }

    /** @param list<array{0: string, 1: int|float, 2?: string, 3?: string}> $rows rótulo, valor, texto, tom (ok|warn|crit) */
    public static function bars(array $rows, int|float|null $max = null): array
    {
        $max ??= max(array_merge([1], array_column($rows, 1)));

        return ['type' => 'bars', 'rows' => $rows, 'max' => $max ?: 1];
    }

    public static function cols(array $labels, array $a, array $b, string $nameA, string $nameB): array
    {
        return ['type' => 'cols', 'labels' => $labels, 'a' => $a, 'b' => $b, 'nameA' => $nameA, 'nameB' => $nameB];
    }

    public static function line(array $labels, array $values, int|float|null $limit = null): array
    {
        return ['type' => 'line', 'labels' => $labels, 'values' => $values, 'limit' => $limit];
    }

    /** @param list<array{0: string, 1: int}> $steps etapa, quantidade (a primeira é a maior) */
    public static function funnel(array $steps): array
    {
        return ['type' => 'funnel', 'steps' => $steps];
    }

    /**
     * @param  list<string>  $headers  prefixe com ">" para alinhar à direita
     * @param  list<list<string|array{pill: string, text: string}>>  $rows
     */
    public static function table(array $headers, array $rows, string $empty = 'Nada por aqui.'): array
    {
        return ['type' => 'table', 'headers' => $headers, 'rows' => $rows, 'empty' => $empty];
    }

    /** @param 'ok'|'warn'|'crit'|'n' $tone */
    public static function pill(string $tone, string $text): array
    {
        return ['pill' => $tone, 'text' => $text];
    }

    /** @param list<array{0: string, 1: string, 2: string}> $items etiqueta, tom (crit|warn|n), mensagem */
    public static function alerts(array $items, string $empty = 'Nenhum alerta.'): array
    {
        return ['type' => 'alerts', 'items' => $items, 'empty' => $empty];
    }
}
