<?php

namespace App\Dashboards;

/** Construtores dos 7 tipos de payload que as views em resources/views/dashboards/types sabem desenhar. */
class Payload
{
    /** @param 'up'|'dn'|'n' $tone up = verde, dn = vermelho, n = cinza */
    /** `$spark` (opcional): serie numerica desenhada como minigrafico de area abaixo do numero. */
    public static function kpi(string|int|float $value, string $unit = '', string $delta = '', string $tone = 'n', ?array $bar = null, ?array $spark = null): array
    {
        return ['type' => 'kpi', 'value' => $value, 'unit' => $unit, 'delta' => $delta, 'tone' => $tone, 'bar' => $bar, 'spark' => $spark];
    }

    /**
     * Anel (donut) com legenda. `$fatias`: lista de [rotulo, valor, cor css]. O centro mostra o total/legenda.
     */
    public static function donut(array $fatias, string $centro, string $centroSub = ''): array
    {
        return ['type' => 'donut', 'slices' => array_values($fatias), 'center' => $centro, 'centerSub' => $centroSub];
    }

    /**
     * Mapa de calor (linhas x colunas). `$linhas`: lista de [rotulo, subrotulo, valores[], total (texto), pill|null].
     */
    public static function heat(array $colunas, array $linhas, string $unidade = 'R$'): array
    {
        $max = 0;
        foreach ($linhas as $l) {
            $max = max($max, max(array_merge([0], $l[2])));
        }

        return ['type' => 'heat', 'columns' => $colunas, 'rows' => $linhas, 'max' => $max ?: 1, 'unit' => $unidade];
    }

    /**
     * Ranking com barras de progresso e marcas de limiar. `$linhas`: lista de [rotulo, subrotulo, pct, valor (texto), tom].
     * `$marcas`: percentuais onde desenhar uma linha vertical (ex.: [40, 60]).
     */
    public static function ranking(array $linhas, array $marcas = [], string $nota = ''): array
    {
        return ['type' => 'ranking', 'rows' => $linhas, 'marks' => $marcas, 'note' => $nota];
    }

    /**
     * Mapa (Leaflet). `$pontos`: lista de [lat, lng, cor css, titulo, linha 2]. `$legenda`: lista de [rotulo, cor].
     */
    public static function map(array $pontos, array $legenda = [], ?string $link = null, string $linkRotulo = ''): array
    {
        return ['type' => 'map', 'points' => array_values($pontos), 'legend' => array_values($legenda), 'link' => $link, 'linkLabel' => $linkRotulo];
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
