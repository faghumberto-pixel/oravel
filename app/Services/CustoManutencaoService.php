<?php

namespace App\Services;

use App\Filament\Resources\AssetResource;
use App\Filament\Resources\MaintenanceOrderResource;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Custo de manutencao por equipamento ao longo do tempo (Inteligencia de Ativos).
 *
 * Fonte unica: maintenance_orders.total_order_cost (mao de obra + material + logistica, ja mantido pelo
 * sistema). O mes de cada custo e' o da conclusao da OS (ou da abertura, se ainda nao concluida). Usado pela aba
 * "Custos e historico" do cadastro do equipamento e pela tela de ranking.
 */
class CustoManutencaoService
{
    /** Chave de contrato que libera as telas de custo (config/menu_modules.php). */
    public const FEATURE = 'menu_custo_equipamentos';

    private const MESES = [1 => 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    public static function habilitado(): bool
    {
        $tenant = Tenancy::current();

        return $tenant !== null && $tenant->hasFeature(self::FEATURE);
    }

    /** @return array<string, mixed> */
    public function resumo(Asset $asset): array
    {
        $ordens = $this->carregar($asset);

        $total = (float) $ordens->sum('total');
        $valor = (float) ($asset->acquisition_value ?? 0);
        $razao = $valor > 0 ? $total / $valor : null;

        $janela = (int) config('custo_manutencao.janela_tendencia_meses', 6);
        $corte = Carbon::now()->subMonths($janela);
        $corteAnterior = Carbon::now()->subMonths($janela * 2);
        $recentes = (float) $ordens->filter(fn ($o) => $o['data']->gte($corte))->sum('total');
        $anteriores = (float) $ordens->filter(fn ($o) => $o['data']->gte($corteAnterior) && $o['data']->lt($corte))->sum('total');

        return [
            'total' => round($total, 2),
            'mao_de_obra' => round((float) $ordens->sum('labor'), 2),
            'material' => round((float) $ordens->sum('material'), 2),
            'logistica' => round((float) $ordens->sum('logistics'), 2),
            'os_total' => $ordens->count(),
            'os_preventivas' => $ordens->where('tipo', 'Preventiva')->count(),
            'os_corretivas' => $ordens->where('tipo', '!=', 'Preventiva')->count(),
            'retrabalho' => $ordens->where('retrabalho', true)->count(),
            'em_aberto' => $ordens->filter(fn ($o) => ! in_array($o['status'], ['Concluída', 'Completado', 'Cancelada'], true))->count(),
            'custo_medio_os' => $ordens->count() ? round($total / $ordens->count(), 2) : 0.0,
            'valor_aquisicao' => $valor,
            'valor_atual' => (float) ($asset->getDepreciationData()['current_value'] ?? $valor),
            'razao' => $razao,
            'janela_meses' => $janela,
            'custo_recente' => round($recentes, 2),
            'custo_anterior' => round($anteriores, 2),
            'variacao_tendencia' => $anteriores > 0 ? round(($recentes - $anteriores) / $anteriores * 100) : null,
            'primeira_data' => $ordens->min('data'),
            'ultima_data' => $ordens->max('data'),
            'situacao' => self::situacao($razao),
        ];
    }

    /**
     * Sinalizacao por custo acumulado / valor de aquisicao (limiares em config/custo_manutencao.php).
     *
     * @return array{chave: string, rotulo: string, cor: string}
     */
    public static function situacao(?float $razao): array
    {
        if ($razao !== null && $razao >= (float) config('custo_manutencao.limiar_trocar_vender', 0.60)) {
            return ['chave' => 'trocar_vender', 'rotulo' => 'Considerar trocar ou vender', 'cor' => 'red'];
        }
        if ($razao !== null && $razao >= (float) config('custo_manutencao.limiar_revisar', 0.40)) {
            return ['chave' => 'revisar', 'rotulo' => 'Revisar', 'cor' => 'amber'];
        }

        return ['chave' => 'ok', 'rotulo' => $razao === null ? 'Sem valor de aquisição' : 'Custo sob controle', 'cor' => $razao === null ? 'gray' : 'emerald'];
    }

    /**
     * Custo mes a mes dos ultimos $meses meses (incluindo o atual), com o acumulado desde o inicio do historico.
     *
     * @return array<int, array<string, mixed>>
     */
    public function porMes(Asset $asset, int $meses = 18): array
    {
        $ordens = $this->carregar($asset);
        $inicio = Carbon::now()->startOfMonth()->subMonths($meses - 1);
        $acumulado = (float) $ordens->filter(fn ($o) => $o['data']->lt($inicio))->sum('total');

        $linhas = [];
        for ($i = 0; $i < $meses; $i++) {
            $mes = $inicio->copy()->addMonths($i);
            $doMes = $ordens->filter(fn ($o) => $o['data']->isSameMonth($mes));
            $totalMes = (float) $doMes->sum('total');
            $acumulado += $totalMes;

            $linhas[] = [
                'mes' => $mes->format('Y-m'),
                'rotulo' => self::MESES[$mes->month].'/'.$mes->format('y'),
                'os' => $doMes->count(),
                'mao_de_obra' => round((float) $doMes->sum('labor'), 2),
                'material' => round((float) $doMes->sum('material'), 2),
                'logistica' => round((float) $doMes->sum('logistics'), 2),
                'total' => round($totalMes, 2),
                'acumulado' => round($acumulado, 2),
            ];
        }

        return $linhas;
    }

    /** OS do equipamento, da mais recente para a mais antiga. */
    public function ordens(Asset $asset, int $limite = 100): Collection
    {
        return $this->carregar($asset)->sortByDesc('data')->take($limite)->values();
    }

    /**
     * Ranking de custo de manutencao de TODOS os equipamentos do tenant atual (uma consulta de OS agrupada por
     * equipamento e mes). Cada linha traz o custo do periodo, o acumulado, o % do valor de aquisicao, a tendencia,
     * a serie dos ultimos 12 meses e a sinalizacao.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function ranking(int $meses = 12, ?string $categoria = null, ?string $busca = null): Collection
    {
        $janela = (int) config('custo_manutencao.janela_tendencia_meses', 6);
        $inicioPeriodo = Carbon::now()->startOfMonth()->subMonths(max(1, $meses) - 1)->format('Y-m');
        $inicioRecente = Carbon::now()->startOfMonth()->subMonths($janela - 1)->format('Y-m');
        $inicioAnterior = Carbon::now()->startOfMonth()->subMonths($janela * 2 - 1)->format('Y-m');
        $serieMeses = collect(range(11, 0))->map(fn ($i) => Carbon::now()->startOfMonth()->subMonths($i)->format('Y-m'));

        $porAtivo = MaintenanceOrder::query()
            ->selectRaw("asset_id, to_char(coalesce(finished_at, created_at), 'YYYY-MM') as mes, count(*) as os, sum(total_order_cost) as total, sum(case when is_rework then 1 else 0 end) as retrabalho")
            ->groupBy('asset_id', 'mes')
            ->get()
            ->groupBy('asset_id');

        $ativos = Asset::query()
            ->when($categoria, fn ($q) => $q->where('asset_category', $categoria))
            ->when($busca, fn ($q) => $q->where(fn ($w) => $w->where('name', 'ilike', "%{$busca}%")->orWhere('patrimonio', 'ilike', "%{$busca}%")->orWhere('tag', 'ilike', "%{$busca}%")))
            ->get(['id', 'name', 'patrimonio', 'asset_category', 'status', 'acquisition_value']);

        return $ativos->map(function (Asset $a) use ($porAtivo, $inicioPeriodo, $inicioRecente, $inicioAnterior, $serieMeses, $janela) {
            $linhas = $porAtivo->get($a->id, collect());
            $somar = fn ($filtro, $campo = 'total') => (float) $linhas->filter($filtro)->sum($campo);

            $valor = (float) ($a->acquisition_value ?? 0);
            $total = $somar(fn () => true);
            $razao = $valor > 0 ? $total / $valor : null;
            $recente = $somar(fn ($l) => $l->mes >= $inicioRecente);
            $anterior = $somar(fn ($l) => $l->mes >= $inicioAnterior && $l->mes < $inicioRecente);
            $porMes = $linhas->keyBy('mes');

            return [
                'id' => (string) $a->id,
                'nome' => $a->name,
                'patrimonio' => $a->patrimonio,
                'categoria' => $a->asset_category,
                'status' => $a->status,
                'valor' => $valor,
                'custo_periodo' => round($somar(fn ($l) => $l->mes >= $inicioPeriodo), 2),
                'os_periodo' => (int) $somar(fn ($l) => $l->mes >= $inicioPeriodo, 'os'),
                'custo_total' => round($total, 2),
                'os_total' => (int) $somar(fn () => true, 'os'),
                'retrabalho' => (int) $somar(fn () => true, 'retrabalho'),
                'razao' => $razao,
                'variacao' => $anterior > 0 ? (int) round(($recente - $anterior) / $anterior * 100) : null,
                'janela' => $janela,
                'serie' => $serieMeses->map(fn ($m) => round((float) ($porMes->get($m)->total ?? 0), 2))->all(),
                'situacao' => self::situacao($razao),
                'url' => AssetResource::getUrl('edit', ['record' => $a->id]),
            ];
        })->values();
    }

    /**
     * Linha do tempo do equipamento: locacoes (contratos) e ordens de servico no mesmo eixo de meses. Posicoes em
     * percentual da largura (0 a 100) para o HTML desenhar sem biblioteca de grafico.
     *
     * @return array<string, mixed>
     */
    public function linhaDoTempo(Asset $asset, int $meses = 18): array
    {
        $inicio = Carbon::now()->startOfMonth()->subMonths($meses - 1);
        $fim = Carbon::now()->endOfDay();
        $dias = max(1, $inicio->diffInDays($fim));
        $pos = fn (Carbon $d): float => round(max(0, min(100, $inicio->diffInDays($d, false) / $dias * 100)), 2);

        $ordens = $this->carregar($asset)->filter(fn ($o) => $o['data']->gte($inicio))->values();
        $maiorCusto = max(1.0, (float) $ordens->max('total'));

        $diasLocado = 0;
        $contratos = $asset->contracts()->with('client:id,name')->orderBy('start_date')
            ->get(['id', 'client_id', 'contract_number', 'status', 'start_date', 'end_date', 'price', 'cidade_obra'])
            ->map(function ($c) use ($inicio, $fim, $pos, &$diasLocado) {
                $de = Carbon::parse($c->start_date)->startOfDay();
                $ativo = $c->status === 'Ativo';
                $ate = $c->end_date ? Carbon::parse($c->end_date)->endOfDay() : $fim->copy();
                $ate = $ate->gt($fim) ? $fim->copy() : $ate;
                if ($ate->lt($inicio) || $de->gt($fim)) {
                    return null;
                }
                $de = $de->lt($inicio) ? $inicio->copy() : $de;
                $diasLocado += max(0, $de->diffInDays($ate));
                $esq = $pos($de);

                return [
                    'numero' => $c->contract_number,
                    'cliente' => $c->client?->name ?? 'Cliente',
                    'cidade' => $c->cidade_obra,
                    'de' => Carbon::parse($c->start_date),
                    'ate' => $c->end_date ? Carbon::parse($c->end_date) : null,
                    'valor' => (float) $c->price,
                    'ativo' => $ativo,
                    'esq' => $esq,
                    'larg' => max(0.8, round($pos($ate) - $esq, 2)),
                ];
            })->filter()->values();

        $eixo = collect(range(0, $meses - 1))->map(function ($i) use ($inicio, $pos) {
            $mes = $inicio->copy()->addMonths($i);

            return ['rotulo' => self::MESES[$mes->month].'/'.$mes->format('y'), 'esq' => $pos($mes), 'mostrar' => $i % 2 === 0];
        })->all();

        $receita = (float) $asset->contracts()->sum('price');
        $custo = (float) $this->carregar($asset)->sum('total');

        return [
            'meses' => $meses,
            'contratos' => $contratos->all(),
            'ordens' => $ordens->map(fn ($o) => [
                'numero' => $o['numero'], 'tipo' => $o['tipo'], 'descricao' => $o['descricao'], 'data' => $o['data'], 'custo' => $o['total'],
                'retrabalho' => $o['retrabalho'], 'esq' => $pos($o['data']),
                'tamanho' => (int) round(10 + sqrt($o['total'] / $maiorCusto) * 18),
            ])->all(),
            'eixo' => $eixo,
            'taxa_locacao' => (int) round(min(100, $diasLocado / $dias * 100)),
            'receita' => round($receita, 2),
            'custo' => round($custo, 2),
            'saldo' => round($receita - $custo, 2),
        ];
    }

    /** Rotulos dos 12 meses da serie do ranking (do mais antigo ao atual), na mesma ordem de 'serie'. */
    public static function rotulosSerie(): array
    {
        return collect(range(11, 0))->map(function ($i) {
            $mes = Carbon::now()->startOfMonth()->subMonths($i);

            return self::MESES[$mes->month].'/'.$mes->format('y');
        })->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function carregar(Asset $asset): Collection
    {
        return $asset->maintenanceOrders()
            ->get(['id', 'os_number', 'maintenance_type', 'description', 'status', 'is_rework', 'labor_cost', 'material_cost', 'logistics_cost', 'total_order_cost', 'created_at', 'finished_at'])
            ->map(fn ($o) => [
                'id' => $o->id,
                'numero' => $o->os_number,
                'tipo' => $o->maintenance_type ?: 'Corretiva',
                'descricao' => $o->description,
                'status' => $o->status,
                'retrabalho' => (bool) $o->is_rework,
                'labor' => (float) $o->labor_cost,
                'material' => (float) $o->material_cost,
                'logistics' => (float) $o->logistics_cost,
                'total' => (float) $o->total_order_cost,
                'data' => Carbon::parse($o->finished_at ?? $o->created_at),
                'url' => MaintenanceOrderResource::getUrl('edit', ['record' => $o->id]),
            ]);
    }
}
