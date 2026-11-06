<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\FrotaSaidaVeiculo;

class UsoPorVeiculo extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.uso_por_veiculo';
    }

    public static function title(): string
    {
        return 'Uso por veículo';
    }

    public static function subtitle(): string
    {
        return 'Km rodado de cada veículo nos últimos 60 dias';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $nomes = $this->veiculos()->pluck('name', 'id');
        $saidas = FrotaSaidaVeiculo::query()->whereNotNull('retorno_em')->where('saida_em', '>=', now()->subDays(60))->get();
        $porVeiculo = $saidas->groupBy(fn ($s) => (string) $s->ativo_id)->map(fn ($g) => $g->sum(fn ($s) => $this->kmDaSaida($s)))->sortDesc();

        return Payload::ranking($this->linhasDeRanking($porVeiculo->mapWithKeys(fn ($km, $id) => [($nomes[$id] ?? 'Veículo removido') => $km]), fn ($km) => number_format($km, 0, ',', '.').' km', 'a'), [], 'A barra compara com o veículo mais rodado.');
    }
}
