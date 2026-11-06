<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\FrotaSaidaVeiculo;

class SaidasPorDiaDaSemana extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.saidas_calor';
    }

    public static function title(): string
    {
        return 'Quando a frota sai';
    }

    public static function subtitle(): string
    {
        return 'Mapa de calor das saídas por veículo e dia da semana, últimos 60 dias';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $saidas = FrotaSaidaVeiculo::query()->where('saida_em', '>=', now()->subDays(60))->get(['ativo_id', 'saida_em']);
        $nomes = $this->veiculos()->pluck('name', 'id');
        $dias = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

        $linhas = $nomes->map(function ($nome, $id) use ($saidas) {
            $valores = [];
            for ($d = 1; $d <= 7; $d++) {
                $valores[] = $saidas->filter(fn ($s) => (string) $s->ativo_id === (string) $id && $s->saida_em->dayOfWeekIso === $d)->count();
            }

            return [$nome, '', $valores, array_sum($valores).' saídas', null];
        })->values()->all();

        return Payload::heat($dias, $linhas, '', 'Mais saídas no dia');
    }
}
