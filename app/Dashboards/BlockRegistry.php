<?php

namespace App\Dashboards;

use App\Dashboards\Blocks\Manutencao;
use App\Dashboards\Blocks\MateriaisPecas;

/**
 * Catálogo de blocos. Lista explícita (e não descoberta por pasta) para que uma classe nova nunca apareça
 * no catálogo sem ser registrada de propósito.
 */
class BlockRegistry
{
    /** @return array<string, class-string<Block>> chave => classe */
    public static function all(): array
    {
        $classes = [
            Manutencao\AtendimentosHoje::class,
            Manutencao\EquipamentosParados::class,
            Manutencao\AtendimentosNoPrazo::class,
            Manutencao\RetornoMaquina::class,
            Manutencao\KmRodados::class,
            Manutencao\CustoMedio::class,
            Manutencao\AgendaDeHoje::class,
            Manutencao\AtendimentosPorSituacao::class,
            Manutencao\TipoDeAtendimento::class,
            Manutencao\TempoPrimeiroAtendimento::class,
            Manutencao\DefeitosFrequentes::class,
            Manutencao\AguardandoPeca::class,

            MateriaisPecas\ValorEmEstoque::class,
            MateriaisPecas\GiroDoEstoque::class,
            MateriaisPecas\ItensAbaixoDoMinimo::class,
            MateriaisPecas\ComprasAReceber::class,
            MateriaisPecas\AbaixoDoMinimoSugestao::class,
            MateriaisPecas\EstoquePorCategoria::class,
            MateriaisPecas\MateriaisMaisConsumidos::class,
            MateriaisPecas\CoberturaEmDias::class,
            MateriaisPecas\AplicadasEOrcadas::class,
            MateriaisPecas\RequisicoesEmAberto::class,
        ];

        $porChave = [];
        foreach ($classes as $classe) {
            $porChave[$classe::key()] = $classe;
        }

        return $porChave;
    }

    /** @return class-string<Block>|null */
    public static function find(string $key): ?string
    {
        return static::all()[$key] ?? null;
    }
}
