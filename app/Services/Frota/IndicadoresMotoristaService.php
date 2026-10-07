<?php

namespace App\Services\Frota;

use App\Models\FleetDriver;
use App\Models\FrotaAbastecimento;
use App\Models\FrotaMulta;
use App\Models\FrotaSaidaVeiculo;
use App\Models\FrotaSinistro;

/**
 * Indicadores por motorista no período de meses cheios: saídas, km rodado, multas e pontos, sinistros (e os de culpa própria),
 * consumo médio e ocorrências por 1.000 km. São números para conversar com o motorista, não uma "nota": o contexto
 * (rota, carga, veículo) importa. Tudo calculado na hora.
 */
class IndicadoresMotoristaService
{
    /**
     * @return array{saidas: int, km: int, multas: int, pontos: int, sinistros: int, sinistros_culpa_propria: int, consumo_km_l: ?float, ocorrencias_por_mil_km: ?float, pontos_12_meses: int}
     */
    public function motorista(FleetDriver $m, int $meses = 3): array
    {
        [$ini, $fim] = CustoFrotaService::periodo($meses);

        $saidas = FrotaSaidaVeiculo::where('motorista_id', $m->id)->whereBetween('saida_em', [$ini, $fim])->get();
        $km = (int) $saidas->whereNotNull('odometro_retorno')->sum(fn (FrotaSaidaVeiculo $s) => max(0, $s->odometro_retorno - $s->odometro_saida));
        $multas = FrotaMulta::where('motorista_id', $m->id)->whereBetween('infracao_em', [$ini, $fim])->where('situacao', '!=', FrotaMulta::CANCELADA)->get();
        $sinistros = FrotaSinistro::where('motorista_id', $m->id)->whereBetween('ocorrido_em', [$ini, $fim])->where('situacao', '!=', FrotaSinistro::CANCELADO)->get();
        $consumo = FrotaAbastecimento::where('motorista_id', $m->id)->whereBetween('abastecido_em', [$ini, $fim])->whereNotNull('consumo_km_l')->avg('consumo_km_l');
        $ocorrencias = $multas->count() + $sinistros->count();

        return [
            'saidas' => $saidas->count(),
            'km' => $km,
            'multas' => $multas->count(),
            'pontos' => (int) $multas->sum('pontos'),
            'sinistros' => $sinistros->count(),
            'sinistros_culpa_propria' => $sinistros->where('culpa', 'propria')->count(),
            'consumo_km_l' => $consumo !== null ? round((float) $consumo, 2) : null,
            'ocorrencias_por_mil_km' => $km > 0 ? round($ocorrencias / $km * 1000, 2) : null,
            'pontos_12_meses' => MultaService::pontosDoMotorista($m),
        ];
    }
}
