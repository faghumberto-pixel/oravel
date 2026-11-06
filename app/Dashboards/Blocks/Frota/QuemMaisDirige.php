<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\FrotaSaidaVeiculo;

class QuemMaisDirige extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.quem_mais_dirige';
    }

    public static function title(): string
    {
        return 'Quem mais dirige';
    }

    public static function subtitle(): string
    {
        return 'Km rodado por motorista nos últimos 60 dias';
    }

    public static function size(): int
    {
        return 4;
    }

    public function data(array $config): array
    {
        $saidas = FrotaSaidaVeiculo::query()->whereNotNull('retorno_em')->where('saida_em', '>=', now()->subDays(60))->get();
        $porMotorista = $saidas->groupBy(fn ($s) => $s->condutor_nome ?: 'Não informado')->map(fn ($g) => $g->sum(fn ($s) => $this->kmDaSaida($g->first() === $s ? $s : $s)))->sortDesc()->take(6);

        return Payload::ranking($this->linhasDeRanking($porMotorista, fn ($km) => number_format($km, 0, ',', '.').' km', 'ok'), [], 'Pelas saídas concluídas; a barra compara com quem mais rodou.');
    }
}
