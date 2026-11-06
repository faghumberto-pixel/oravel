<?php

namespace App\Services\Estoque;

use App\Models\AggregateItem;
use App\Models\AggregateItemType;
use App\Models\Material;
use App\Models\Part;
use App\Models\StockPlacement;
use App\Models\WarehouseStock;
use Illuminate\Support\Collection;

/**
 * Visao unica do estoque da empresa: junta os tres cadastros que existem (Materiais, Pecas/oleo da frota e Itens Agregados)
 * em linhas do mesmo formato, com grupo, categoria, saldo, valor, situacao e enderecos. So' le: nao altera nenhum cadastro.
 */
class EstoqueUnificado
{
    public const MATERIAL = StockPlacement::MATERIAL;

    public const PART = StockPlacement::PART;

    public const AGGREGATE = StockPlacement::AGGREGATE;

    /**
     * @param  array{grupo?: ?string, tipo?: ?string, categoria?: ?string, unidade?: ?string, corredor?: ?string, situacao?: ?string, endereco?: ?string, busca?: ?string}  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    public function itens(array $filtros = []): Collection
    {
        $colocacoes = $this->colocacoes();

        $linhas = $this->materiais($colocacoes)->concat($this->pecas($colocacoes))->concat($this->agregados($colocacoes));

        return $this->filtrar($linhas, $filtros)->sortBy([['grupo', 'asc'], ['nome', 'asc']])->values();
    }

    /** @return Collection<int, array<string, mixed>> todas as linhas, sem filtro (para montar as opcoes dos filtros) */
    public function todos(): Collection
    {
        return $this->itens();
    }

    /**
     * Resumo de um conjunto de linhas (ja filtradas).
     *
     * @return array{itens: int, valor: float, abaixo: int, zerados: int, sem_endereco: int, por_grupo: Collection, por_categoria: Collection, por_tipo: Collection}
     */
    public function resumo(Collection $linhas): array
    {
        return [
            'itens' => $linhas->count(),
            'valor' => (float) $linhas->sum('valor'),
            'abaixo' => $linhas->where('situacao', 'abaixo')->count(),
            'zerados' => $linhas->where('situacao', 'zerado')->count(),
            'sem_endereco' => $linhas->where('sem_endereco', true)->count(),
            'por_grupo' => $linhas->groupBy('grupo')->map(fn ($g) => ['itens' => $g->count(), 'valor' => (float) $g->sum('valor'), 'cor' => $g->first()['grupo_cor']])->sortByDesc('valor'),
            'por_categoria' => $linhas->groupBy('categoria')->map(fn ($g) => ['itens' => $g->count(), 'valor' => (float) $g->sum('valor')])->sortByDesc('valor'),
            'por_tipo' => $linhas->groupBy('tipo_rotulo')->map(fn ($g) => $g->count()),
        ];
    }

    /** @return Collection<string, list<array<string, mixed>>> "tipo|id" => enderecos onde o item esta */
    private function colocacoes(): Collection
    {
        return StockPlacement::query()->with('location:id,internal_unit_id,aisle,code')->get()
            ->filter(fn ($p) => $p->location)
            ->groupBy(fn ($p) => $p->item_type.'|'.$p->item_id)
            ->map(fn ($g) => $g->map(fn ($p) => ['codigo' => $p->location->code, 'unidade' => (string) $p->location->internal_unit_id, 'corredor' => $p->location->aisle, 'quantidade' => (float) $p->quantity])->values()->all());
    }

    private function linha(string $tipo, string $rotulo, string|int $id, ?string $sku, string $nome, $grupo, ?string $categoria, ?string $unidade, float $quantidade, float $minimo, float $custo, Collection $colocacoes): array
    {
        $ends = $colocacoes->get($tipo.'|'.$id, []);

        return [
            'tipo' => $tipo, 'tipo_rotulo' => $rotulo, 'id' => (string) $id, 'sku' => $sku, 'nome' => $nome,
            'grupo_id' => $grupo?->id ? (string) $grupo->id : null, 'grupo' => $grupo?->name ?? 'Sem grupo', 'grupo_cor' => $grupo?->color ?? '#64748b',
            'categoria' => $categoria ?: 'Sem categoria', 'unidade' => $unidade ?: 'un',
            'quantidade' => $quantidade, 'minimo' => $minimo, 'custo' => $custo, 'valor' => round($quantidade * $custo, 2),
            'situacao' => $quantidade <= 0 ? 'zerado' : ($minimo > 0 && $quantidade < $minimo ? 'abaixo' : 'ok'),
            'alocado' => ($alocado = array_sum(array_column($ends, 'quantidade'))), 'nao_alocado' => $ends === [] ? 0.0 : max(0.0, round($quantidade - $alocado, 3)),
            'enderecos' => array_column($ends, 'codigo'), 'unidades' => array_values(array_unique(array_column($ends, 'unidade'))),
            'corredores' => array_values(array_unique(array_column($ends, 'corredor'))), 'sem_endereco' => $ends === [],
        ];
    }

    private function materiais(Collection $c): Collection
    {
        return Material::query()->with(['category:id,name', 'stockGroup:id,name,color'])->get()
            ->map(fn (Material $m) => $this->linha(self::MATERIAL, 'Material', $m->id, $m->sku, $m->name, $m->stockGroup, $m->category?->name, $m->unit_of_measure, (float) $m->current_stock, (float) $m->min_stock, (float) $m->unit_cost, $c));
    }

    private function pecas(Collection $c): Collection
    {
        $pecas = Part::query()->with(['category:id,name', 'stockGroup:id,name,color'])->get();
        $saldos = WarehouseStock::query()->whereIn('part_id', $pecas->pluck('id'))->selectRaw('part_id, sum(current_quantity) as total')->groupBy('part_id')->pluck('total', 'part_id');

        return $pecas->map(fn (Part $p) => $this->linha(self::PART, 'Peça / óleo', $p->id, $p->sku, $p->name, $p->stockGroup, $p->category?->name, $p->unit_of_measure, (float) ($saldos[$p->id] ?? 0), (float) $p->minimum_stock, (float) $p->cost_price, $c));
    }

    private function agregados(Collection $c): Collection
    {
        $tipos = AggregateItemType::query()->with('stockGroup:id,name,color')->get();
        $itens = AggregateItem::query()->get(['id', 'aggregate_item_type_id', 'purchase_value'])->groupBy('aggregate_item_type_id');

        return $tipos->map(function (AggregateItemType $t) use ($itens, $c) {
            $do = $itens->get($t->id, collect());
            $qtd = (float) $do->count();
            $custo = $qtd > 0 ? (float) $do->sum('purchase_value') / $qtd : 0.0;

            return $this->linha(self::AGGREGATE, 'Item agregado', $t->id, null, $t->name, $t->stockGroup, $t->category ? ucfirst((string) $t->category) : null, $t->unit_of_measure, $qtd, 0.0, $custo, $c);
        })->filter(fn ($l) => $l['quantidade'] > 0 || $l['enderecos'] !== []);
    }

    private function filtrar(Collection $linhas, array $f): Collection
    {
        return $linhas
            ->when(filled($f['grupo'] ?? null), fn ($l) => $l->filter(fn ($i) => $f['grupo'] === 'sem' ? $i['grupo_id'] === null : $i['grupo_id'] === $f['grupo']))
            ->when(filled($f['tipo'] ?? null), fn ($l) => $l->where('tipo', $f['tipo']))
            ->when(filled($f['categoria'] ?? null), fn ($l) => $l->where('categoria', $f['categoria']))
            ->when(filled($f['unidade'] ?? null), fn ($l) => $l->filter(fn ($i) => in_array($f['unidade'], $i['unidades'], true)))
            ->when(filled($f['corredor'] ?? null), fn ($l) => $l->filter(fn ($i) => in_array($f['corredor'], $i['corredores'], true)))
            ->when(filled($f['situacao'] ?? null), fn ($l) => $l->where('situacao', $f['situacao']))
            ->when(($f['endereco'] ?? null) === 'sem', fn ($l) => $l->where('sem_endereco', true))
            ->when(filled($f['busca'] ?? null), function ($l) use ($f) {
                $b = mb_strtolower(trim((string) $f['busca']));

                return $l->filter(fn ($i) => str_contains(mb_strtolower($i['nome'].' '.($i['sku'] ?? '')), $b));
            });
    }
}
