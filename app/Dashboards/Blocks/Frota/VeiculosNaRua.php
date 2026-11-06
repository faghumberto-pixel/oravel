<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\FrotaSaidaVeiculo;

class VeiculosNaRua extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.veiculos_na_rua';
    }

    public static function title(): string
    {
        return 'Na rua agora';
    }

    public static function subtitle(): string
    {
        return 'Veículos que saíram e ainda não voltaram';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $fora = $this->naRua();
        $longe = $fora->filter(fn ($s) => $this->horasFora($s) >= FrotaSaidaVeiculo::AVISO_FORA_HORAS)->count();
        $serie = $this->serieMensal(FrotaSaidaVeiculo::query(), 'saida_em', 6);

        return Payload::kpi($fora->count(), $fora->count() === 1 ? 'veículo' : 'veículos', $longe > 0 ? $longe.' fora há mais de '.FrotaSaidaVeiculo::AVISO_FORA_HORAS.' h' : 'Todos dentro do prazo', $longe > 0 ? 'dn' : 'n', null, $serie);
    }
}
