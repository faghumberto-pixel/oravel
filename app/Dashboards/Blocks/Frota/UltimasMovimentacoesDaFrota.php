<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\FrotaSaidaVeiculo;

class UltimasMovimentacoesDaFrota extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.ultimas_movimentacoes';
    }

    public static function title(): string
    {
        return 'Últimas saídas e retornos';
    }

    public static function subtitle(): string
    {
        return 'Linha do tempo do que aconteceu com a frota, do mais recente ao mais antigo';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $saidas = FrotaSaidaVeiculo::query()->where('saida_em', '>=', now()->subDays(14))->orderByDesc('saida_em')->limit(12)->get();
        $nomes = $this->veiculos()->pluck('name', 'id');
        $eventos = [];
        foreach ($saidas as $s) {
            $nome = $nomes[$s->ativo_id] ?? 'Veículo';
            $eventos[] = [$s->saida_em, 'Saiu — '.$nome, ($s->condutor_nome ?: 'motorista não informado').' → '.$s->destino, 'warn', 'saída'];
            if ($s->retorno_em) {
                $eventos[] = [$s->retorno_em, 'Voltou — '.$nome, ($s->condutor_nome ?: 'motorista não informado').' · '.$this->kmDaSaida($s).' km rodados', 'ok', 'retorno'];
            }
        }
        usort($eventos, fn ($a, $b) => $b[0] <=> $a[0]);

        return Payload::timeline(array_map(fn ($e) => [$e[0]->format('d/m H\hi'), $e[1], $e[2], $e[3], $e[4]], array_slice($eventos, 0, 8)), 'Nenhuma saída nos últimos 14 dias.');
    }
}
