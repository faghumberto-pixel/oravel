<?php

namespace App\Services\Frota;

use App\Models\Asset;
use App\Models\FrotaAbastecimento;
use App\Models\FrotaCustoAvulso;
use App\Models\FrotaInstalacaoComponente;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaMulta;
use App\Models\FrotaSinistro;
use App\Models\FrotaTrocaOleo;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Carbon;

/**
 * Custo total por veículo num período de meses cheios (do 1º dia do mês inicial até agora): combustível, manutenção (OS),
 * pneus, baterias, óleo, multas (as da empresa), sinistros (franquia; ou orçamento se não há OS nem franquia) e custos avulsos
 * (seguro, IPVA... rateados por mês). Tudo calculado na hora, nada é gravado.
 */
class CustoFrotaService
{
    /** @return array<string, string> */
    public static function componentes(): array
    {
        return ['combustivel' => 'Combustível', 'manutencao' => 'Manutenção', 'pneus' => 'Pneus', 'baterias' => 'Baterias', 'oleo' => 'Óleo',
            'multas' => 'Multas', 'sinistros' => 'Sinistros', 'avulsos' => 'Seguro, IPVA e outros'];
    }

    /** @return array{0: Carbon, 1: Carbon} início (1º dia do mês) e fim (agora) de um período de N meses cheios, contando o mês atual. */
    public static function periodo(int $meses): array
    {
        return [now()->subMonthsNoOverflow(max(1, $meses) - 1)->startOfMonth(), now()];
    }

    /**
     * @return array{componentes: array<string, float>, total: float, km: ?int, custo_km: ?float}
     */
    public function veiculo(Asset $v, int $meses = 3): array
    {
        [$ini, $fim] = self::periodo($meses);

        $c = [
            'combustivel' => (float) FrotaAbastecimento::where('ativo_id', $v->id)->whereBetween('abastecido_em', [$ini, $fim])->sum('valor_total'),
            'manutencao' => (float) MaintenanceOrder::where('asset_id', $v->id)->whereBetween('created_at', [$ini, $fim])->sum('total_order_cost'),
            'pneus' => $this->instalados($v, 'pneu', $ini, $fim),
            'baterias' => $this->instalados($v, 'bateria', $ini, $fim),
            'oleo' => (float) FrotaTrocaOleo::where('ativo_id', $v->id)->whereBetween('realizado_em', [$ini, $fim])->sum('custo'),
            'multas' => (float) FrotaMulta::where('ativo_id', $v->id)->whereBetween('infracao_em', [$ini, $fim])
                ->where('situacao', '!=', FrotaMulta::CANCELADA)->where('quem_paga', 'empresa')->sum('valor'),
            'sinistros' => $this->sinistros($v, $ini, $fim),
            'avulsos' => round((float) FrotaCustoAvulso::where('ativo_id', $v->id)->where('data', '<=', $fim)
                ->get()->sum(fn (FrotaCustoAvulso $x) => $x->parteNoPeriodo($ini, $fim)), 2),
        ];
        $c = array_map(fn (float $x) => round($x, 2), $c);
        $total = round(array_sum($c), 2);
        $km = $this->km($v, $ini, $fim);

        return ['componentes' => $c, 'total' => $total, 'km' => $km, 'custo_km' => $km ? round($total / $km, 2) : null];
    }

    /** Custo dos pneus/baterias que foram instalados no período (o custo de compra entra quando vai para o veículo). */
    private function instalados(Asset $v, string $tipo, Carbon $ini, Carbon $fim): float
    {
        return (float) FrotaInstalacaoComponente::where('ativo_id', $v->id)->where('componente_type', $tipo)->whereBetween('instalado_em', [$ini, $fim])
            ->with('componente')->get()->sum(fn (FrotaInstalacaoComponente $i) => (float) ($i->componente?->custo ?? 0));
    }

    /** Franquia quando houver; senão o orçamento, mas só se não há OS ligada (para não contar duas vezes o reparo). */
    private function sinistros(Asset $v, Carbon $ini, Carbon $fim): float
    {
        return (float) FrotaSinistro::where('ativo_id', $v->id)->whereBetween('ocorrido_em', [$ini, $fim])->where('situacao', '!=', FrotaSinistro::CANCELADO)->get()
            ->sum(fn (FrotaSinistro $s) => $s->valor_franquia !== null ? (float) $s->valor_franquia : ($s->ordem_servico_id ? 0.0 : (float) ($s->valor_orcamento ?? 0)));
    }

    /** Km do período pelas leituras de odômetro: última leitura menos a base (a última antes do período, ou a primeira dentro dele). */
    private function km(Asset $v, Carbon $ini, Carbon $fim): ?int
    {
        $ultima = FrotaLeituraOdometro::where('ativo_id', $v->id)->where('lido_em', '<=', $fim)->orderByDesc('lido_em')->orderByDesc('id')->value('odometro');
        $base = FrotaLeituraOdometro::where('ativo_id', $v->id)->where('lido_em', '<', $ini)->orderByDesc('lido_em')->orderByDesc('id')->value('odometro')
            ?? FrotaLeituraOdometro::where('ativo_id', $v->id)->whereBetween('lido_em', [$ini, $fim])->orderBy('lido_em')->orderBy('id')->value('odometro');
        if ($ultima === null || $base === null) {
            return null;
        }
        $km = (int) $ultima - (int) $base;

        return $km > 0 ? $km : null;
    }
}
