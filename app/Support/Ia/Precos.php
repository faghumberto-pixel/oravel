<?php

namespace App\Support\Ia;

/**
 * Preco por milhao de tokens (US$) de cada modelo -- tabela de precos da API Anthropic, conferida em
 * 03/11/2026. E' ESTIMATIVA pro painel de consumo; ao trocar de modelo ou se o preco mudar, atualize aqui.
 * Puro (sem config/banco) pra ser testavel sem subir o app.
 */
class Precos
{
    /** @var array<string, array{0: float, 1: float}> modelo => [entrada, saida] */
    public const TABELA = [
        'claude-haiku-5-5' => [0.10, 0.50],
        'claude-haiku-4-5' => [1.00, 5.00],
        'claude-sonnet-5' => [2.00, 10.00],
        'claude-sonnet-5-5' => [2.00, 10.00],
        'claude-sonnet-4-6' => [3.00, 15.00],
        'claude-opus-5-5' => [4.00, 20.00],
        'claude-opus-5' => [5.00, 25.00],
        'claude-opus-4-8' => [5.00, 25.00],
        'claude-opus-4-7' => [5.00, 25.00],
        'claude-opus-4-6' => [5.00, 25.00],
        'claude-fable-5-2' => [8.00, 40.00],
        'claude-fable-5-1' => [10.00, 50.00],
        'claude-fable-5' => [10.00, 50.00],
    ];

    /** Modelo fora da tabela: assume o preco do Sonnet (nao subestima o gasto). */
    public const PADRAO = [2.00, 10.00];

    /** Leitura de cache custa 10% da entrada; escrita de cache, 125%. */
    public const CACHE_LEITURA = 0.10;

    public const CACHE_ESCRITA = 1.25;

    public static function conhecido(string $modelo): bool
    {
        return isset(self::TABELA[$modelo]);
    }

    public static function custoUsd(string $modelo, int $entrada, int $saida, int $cacheLeitura = 0, int $cacheEscrita = 0): float
    {
        [$pEntrada, $pSaida] = self::TABELA[$modelo] ?? self::PADRAO;

        return (
            $entrada * $pEntrada
            + $saida * $pSaida
            + $cacheLeitura * $pEntrada * self::CACHE_LEITURA
            + $cacheEscrita * $pEntrada * self::CACHE_ESCRITA
        ) / 1_000_000;
    }
}
