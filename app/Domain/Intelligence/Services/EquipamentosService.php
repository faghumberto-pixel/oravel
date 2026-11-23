<?php

namespace App\Domain\Intelligence\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Leitura da análise por equipamento (F2): o que as telas "Equipamentos" e "Equipamento" mostram. Sempre do snapshot MAIS RECENTE que
 * tem a aba (ou de um específico) e sempre filtrado pela empresa. Lê só o que já foi calculado e gravado na importação.
 */
class EquipamentosService
{
    public const ORDENS = [
        'gravidade' => 'Mais urgentes primeiro',
        'custo' => 'Maior custo no mês',
        'razao' => 'Maior custo ÷ valor',
        'retorno' => 'Menor retorno',
        'utilizacao' => 'Menor utilização',
        'patrimonio' => 'Nº patrimônio (A–Z)',
    ];

    /** Máximo de bolhas no gráfico valor × receita (acima disso, os de maior valor). */
    private const MAX_BOLHAS = 300;

    /** @return ?array{id: string, data: string} o snapshot mais recente da empresa que tem equipamentos */
    public function snapshot(string $tenantId, ?string $snapshotId = null): ?array
    {
        $q = DB::table('intelligence_snapshots as s')->where('s.tenant_id', $tenantId)
            ->whereExists(fn ($e) => $e->select(DB::raw(1))->from('intelligence_equipamentos')->whereColumn('intelligence_equipamentos.snapshot_id', 's.id'))
            ->orderByDesc('s.data_referencia')->orderByDesc('s.created_at');
        if ($snapshotId) {
            $q->where('s.id', $snapshotId);
        }
        $s = $q->first(['s.id', 's.data_referencia']);

        return $s ? ['id' => $s->id, 'data' => substr((string) $s->data_referencia, 0, 10)] : null;
    }

    private function base(string $tenantId, string $snapshotId): Builder
    {
        return DB::table('intelligence_equipamentos as e')
            ->join('intelligence_equipamento_analises as a', 'a.equipamento_id', '=', 'e.id')
            ->where('e.tenant_id', $tenantId)->where('a.tenant_id', $tenantId)->where('e.snapshot_id', $snapshotId);
    }

    /**
     * Cartões do topo: quantos em cada classificação e quanto valor está parado em "trocar ou vender".
     *
     * @return array{total: int, por_classe: array<string, int>, valor_trocar_vender: float, valor_total: float, categorias: list<string>}
     */
    public function resumo(string $tenantId, string $snapshotId): array
    {
        $b = $this->base($tenantId, $snapshotId);
        $porClasse = (clone $b)->selectRaw('a.classificacao, count(*) as n')->groupBy('a.classificacao')->pluck('n', 'classificacao')->map(fn ($n) => (int) $n)->all();
        $valorTrocar = (float) (clone $b)->where('a.classificacao', 'trocar_vender')->sum('e.valor');
        $valorTotal = (float) (clone $b)->sum('e.valor');
        $cats = (clone $b)->whereNotNull('e.categoria')->distinct()->orderBy('e.categoria')->pluck('e.categoria')->all();

        return ['total' => array_sum($porClasse), 'por_classe' => $porClasse, 'valor_trocar_vender' => $valorTrocar, 'valor_total' => $valorTotal, 'categorias' => $cats];
    }

    /**
     * Linhas da tabela (já filtradas e ordenadas no banco; a tela pede `$limite` por vez).
     *
     * @param  array{busca?: string, categoria?: string, classificacao?: string, ordem?: string}  $filtros
     * @return array{linhas: list<array<string, mixed>>, total: int}
     */
    public function linhas(string $tenantId, string $snapshotId, array $filtros = [], int $limite = 50): array
    {
        $q = $this->filtrar($this->base($tenantId, $snapshotId), $filtros);
        $total = (clone $q)->count();
        $linhas = $this->ordenar($q, $filtros['ordem'] ?? 'gravidade')->limit($limite)
            ->get($this->colunas())->map(fn ($r) => $this->normalizar($r))->all();

        return ['linhas' => $linhas, 'total' => $total];
    }

    /** Todas as linhas do filtro (para Excel e relatório), até o limite do importador. */
    public function todas(string $tenantId, string $snapshotId, array $filtros = [], int $maximo = 5000): array
    {
        return $this->ordenar($this->filtrar($this->base($tenantId, $snapshotId), $filtros), $filtros['ordem'] ?? 'gravidade')->limit($maximo)
            ->get($this->colunas())->map(fn ($r) => $this->normalizar($r))->all();
    }

    /**
     * Pareto do custo do mês: os `$topo` equipamentos de maior custo e a curva acumulada sobre o custo TOTAL da frota.
     *
     * @return array{itens: list<array{patrimonio: string, nome: string, custo: float, acumulado_pct: float, classificacao: string}>, total: float, topo_pct: float}
     */
    public function pareto(string $tenantId, string $snapshotId, int $topo = 20): array
    {
        $b = $this->base($tenantId, $snapshotId)->where('e.custo_mes', '>', 0);
        $total = (float) (clone $b)->sum('e.custo_mes');
        $itens = [];
        $acum = 0.0;
        foreach ((clone $b)->orderByDesc('e.custo_mes')->limit($topo)->get(['e.patrimonio', 'e.nome', 'e.custo_mes', 'a.classificacao']) as $r) {
            $acum += (float) $r->custo_mes;
            $itens[] = ['patrimonio' => $r->patrimonio, 'nome' => $r->nome, 'custo' => (float) $r->custo_mes, 'acumulado_pct' => $total > 0 ? round($acum / $total * 100, 1) : 0.0, 'classificacao' => $r->classificacao];
        }

        return ['itens' => $itens, 'total' => $total, 'topo_pct' => $itens === [] ? 0.0 : end($itens)['acumulado_pct']];
    }

    /**
     * Pontos do gráfico valor × receita (tamanho = custo do mês).
     *
     * @return list<array{patrimonio: string, nome: string, valor: float, receita: float, custo: float, classificacao: string}>
     */
    public function bolhas(string $tenantId, string $snapshotId): array
    {
        return $this->base($tenantId, $snapshotId)->where('e.valor', '>', 0)->whereNotNull('e.receita')->orderByDesc('e.valor')->limit(self::MAX_BOLHAS)
            ->get(['e.patrimonio', 'e.nome', 'e.valor', 'e.receita', 'e.custo_mes', 'a.classificacao'])
            ->map(fn ($r) => ['patrimonio' => $r->patrimonio, 'nome' => $r->nome, 'valor' => (float) $r->valor, 'receita' => (float) $r->receita, 'custo' => (float) ($r->custo_mes ?? 0), 'classificacao' => $r->classificacao])->all();
    }

    /**
     * Um equipamento e o seu histórico (um ponto por fotografia, do mais antigo ao mais novo). Null se a empresa não tem esse patrimônio.
     *
     * @return ?array{atual: array<string, mixed>, historico: list<array<string, mixed>>}
     */
    public function equipamento(string $tenantId, string $patrimonio): ?array
    {
        $pat = mb_strtoupper(trim($patrimonio));
        $rows = DB::table('intelligence_equipamentos as e')
            ->join('intelligence_equipamento_analises as a', 'a.equipamento_id', '=', 'e.id')
            ->join('intelligence_snapshots as s', 's.id', '=', 'e.snapshot_id')
            ->where('e.tenant_id', $tenantId)->where('a.tenant_id', $tenantId)->where('s.tenant_id', $tenantId)->where('e.patrimonio', $pat)
            ->orderBy('s.data_referencia')->orderBy('s.created_at')
            ->get(array_merge($this->colunas(), ['s.data_referencia as data']));
        if ($rows->isEmpty()) {
            return null;
        }
        $historico = $rows->map(fn ($r) => $this->normalizar($r) + ['data' => substr((string) $r->data, 0, 10)])->all();

        return ['atual' => end($historico), 'historico' => $historico];
    }

    private function filtrar(Builder $q, array $f): Builder
    {
        if (filled($f['busca'] ?? null)) {
            $t = '%'.mb_strtolower(trim($f['busca'])).'%';
            $q->where(fn ($w) => $w->whereRaw('lower(e.patrimonio) like ?', [$t])->orWhereRaw('lower(e.nome) like ?', [$t]));
        }
        if (filled($f['categoria'] ?? null)) {
            $q->where('e.categoria', $f['categoria']);
        }
        if (filled($f['classificacao'] ?? null)) {
            $q->where('a.classificacao', $f['classificacao']);
        }

        return $q;
    }

    private function ordenar(Builder $q, string $ordem): Builder
    {
        return match ($ordem) {
            'custo' => $q->orderByRaw('e.custo_mes desc nulls last')->orderBy('e.patrimonio'),
            'razao' => $q->orderByRaw('a.razao_custo_valor desc nulls last')->orderBy('e.patrimonio'),
            'retorno' => $q->orderByRaw('a.retorno_anual_pct asc nulls last')->orderBy('e.patrimonio'),
            'utilizacao' => $q->orderByRaw('a.utilizacao_pct asc nulls last')->orderBy('e.patrimonio'),
            'patrimonio' => $q->orderBy('e.patrimonio'),
            default => $q->orderByRaw("case a.classificacao when 'trocar_vender' then 1 when 'revisar' then 2 when 'risco_parada' then 3 when 'subutilizado' then 4 when 'saudavel' then 5 else 6 end")
                ->orderByRaw('a.razao_custo_valor desc nulls last')->orderBy('e.patrimonio'),
        };
    }

    /** @return list<string> */
    private function colunas(): array
    {
        return ['e.id', 'e.patrimonio', 'e.nome', 'e.categoria', 'e.ano_fabricacao', 'e.valor', 'e.receita', 'e.custo_mes', 'e.custo_acumulado', 'e.os_corretivas', 'e.os_preventivas',
            'e.reincidencias', 'e.dias_locado', 'e.dias_indisponivel', 'a.utilizacao_pct', 'a.disponibilidade_pct', 'a.retorno_anual_pct', 'a.razao_custo_valor',
            'a.custo_sobre_receita_pct', 'a.reincidencia_pct', 'a.idade_anos', 'a.classificacao', 'a.motivo', 'a.tendencia'];
    }

    /** @return array<string, mixed> números como float/int (o banco devolve decimal como texto) */
    private function normalizar(object $r): array
    {
        $f = fn ($v) => $v === null ? null : (float) $v;
        $i = fn ($v) => $v === null ? null : (int) $v;

        return [
            'id' => $r->id, 'patrimonio' => $r->patrimonio, 'nome' => $r->nome, 'categoria' => $r->categoria, 'ano_fabricacao' => $i($r->ano_fabricacao),
            'valor' => $f($r->valor), 'receita' => $f($r->receita), 'custo_mes' => $f($r->custo_mes), 'custo_acumulado' => $f($r->custo_acumulado),
            'os_corretivas' => $i($r->os_corretivas), 'os_preventivas' => $i($r->os_preventivas), 'reincidencias' => $i($r->reincidencias),
            'dias_locado' => $i($r->dias_locado), 'dias_indisponivel' => $i($r->dias_indisponivel),
            'utilizacao_pct' => $f($r->utilizacao_pct), 'disponibilidade_pct' => $f($r->disponibilidade_pct), 'retorno_anual_pct' => $f($r->retorno_anual_pct),
            'razao_custo_valor' => $f($r->razao_custo_valor), 'custo_sobre_receita_pct' => $f($r->custo_sobre_receita_pct), 'reincidencia_pct' => $f($r->reincidencia_pct),
            'idade_anos' => $i($r->idade_anos), 'classificacao' => $r->classificacao, 'motivo' => $r->motivo, 'tendencia' => $r->tendencia,
        ];
    }
}
