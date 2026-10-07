<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\AssetDowntimeEvent;
use App\Models\FrotaSaidaVeiculo;
use App\Models\FrotaSinistro;
use Illuminate\Support\Carbon;

/**
 * Disponibilidade do veículo no período de meses cheios (do 1º dia do mês inicial até agora): % do tempo em que NÃO esteve parado.
 * Parado = paradas do ativo (quebra, manutenção, aguardando peça; "ocioso/sem uso" não conta) + sinistro com veículo parado.
 * Períodos que se sobrepõem são unidos (nada é contado duas vezes). Tudo calculado na hora, nada é gravado.
 */
class DisponibilidadeFrotaService
{
    /** Disponibilidade a partir da meta = verde; abaixo da meta, mas a partir do mínimo = atenção; abaixo = crítico. */
    public const META = 95.0;

    public const MINIMO = 90.0;

    /**
     * @return array{periodo_horas: float, parado_horas: float, disponibilidade: float, paradas: int, fora_horas: float, causa_principal: ?string, situacao: string}
     */
    public function veiculo(Asset $v, int $meses = 3): array
    {
        [$ini, $fim] = CustoFrotaService::periodo($meses);
        $iniTs = $ini->timestamp;
        $fimTs = $fim->timestamp;
        $inicio = max($iniTs, $v->created_at?->timestamp ?? $iniTs);   // veículo cadastrado no meio do período só conta a partir do cadastro
        $total = max(1, $fimTs - $inicio);

        $intervalos = [];
        $porCausa = [];
        $paradas = 0;

        foreach (AssetDowntimeEvent::where('asset_id', $v->id)->where('reason', '!=', AssetDowntimeEvent::REASON_OCIOSO_SEM_USO)
            ->where('started_at', '<=', $fim)->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $ini))->get() as $e) {
            if ($i = $this->recortar($e->started_at, $e->ended_at, $inicio, $fimTs)) {
                $intervalos[] = $i;
                $paradas++;
                $rotulo = AssetDowntimeEvent::reasonLabels()[$e->reason] ?? $e->reason;
                $porCausa[$rotulo] = ($porCausa[$rotulo] ?? 0) + ($i[1] - $i[0]);
            }
        }
        foreach (FrotaSinistro::where('ativo_id', $v->id)->where('veiculo_parado', true)->where('situacao', '!=', FrotaSinistro::CANCELADO)
            ->where('parado_desde', '<=', $fim)->where(fn ($q) => $q->whereNull('voltou_a_rodar_em')->orWhere('voltou_a_rodar_em', '>=', $ini))->get() as $s) {
            if ($i = $this->recortar($s->parado_desde, $s->voltou_a_rodar_em, $inicio, $fimTs)) {
                $intervalos[] = $i;
                $paradas++;
                $porCausa['Sinistro'] = ($porCausa['Sinistro'] ?? 0) + ($i[1] - $i[0]);
            }
        }

        $parado = $this->uniao($intervalos);
        $disp = round(100 * (1 - $parado / $total), 1);
        $fora = 0;
        foreach (FrotaSaidaVeiculo::where('ativo_id', $v->id)->where('saida_em', '<=', $fim)->where(fn ($q) => $q->whereNull('retorno_em')->orWhere('retorno_em', '>=', $ini))->get() as $s) {
            if ($i = $this->recortar($s->saida_em, $s->retorno_em, $inicio, $fimTs)) {
                $fora += $i[1] - $i[0];
            }
        }
        arsort($porCausa);

        return [
            'periodo_horas' => round($total / 3600, 1),
            'parado_horas' => round($parado / 3600, 1),
            'disponibilidade' => $disp,
            'paradas' => $paradas,
            'fora_horas' => round($fora / 3600, 1),
            'causa_principal' => array_key_first($porCausa),
            'situacao' => $disp >= self::META ? 'boa' : ($disp >= self::MINIMO ? 'atencao' : 'critica'),
        ];
    }

    /** @return array{0: int, 1: int}|null [início, fim] em timestamps, dentro do período; null se não toca o período */
    private function recortar(?Carbon $de, ?Carbon $ate, int $inicio, int $fim): ?array
    {
        if (! $de) {
            return null;
        }
        $a = max($de->timestamp, $inicio);
        $b = min($ate?->timestamp ?? $fim, $fim);

        return $b > $a ? [$a, $b] : null;
    }

    /** Soma em segundos da união dos intervalos (sobreposições não contam duas vezes). */
    private function uniao(array $intervalos): int
    {
        usort($intervalos, fn ($x, $y) => $x[0] <=> $y[0]);
        $soma = 0;
        $atual = null;
        foreach ($intervalos as [$a, $b]) {
            if ($atual === null) {
                $atual = [$a, $b];
            } elseif ($a <= $atual[1]) {
                $atual[1] = max($atual[1], $b);
            } else {
                $soma += $atual[1] - $atual[0];
                $atual = [$a, $b];
            }
        }

        return $soma + ($atual ? $atual[1] - $atual[0] : 0);
    }
}
