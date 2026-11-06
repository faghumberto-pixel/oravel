<?php

namespace App\Services\Estoque;

use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockLocation;
use App\Models\StockPlacement;
use Illuminate\Support\Collection;

/**
 * Dados do mapa visual do almoxarifado: cada CORREDOR e' uma coluna, com as prateleiras empilhadas e os niveis de cada uma.
 * Cada celula (endereco) recebe um estado e uma cor conforme o modo: situacao do estoque, grupo ou andamento da contagem.
 */
class MapaDoEstoque
{
    public const MODO_SITUACAO = 'situacao';

    public const MODO_GRUPO = 'grupo';

    public const MODO_CONTAGEM = 'contagem';

    public const CORES = ['vazio' => '#475569', 'ok' => '#16a34a', 'abaixo' => '#f59e0b', 'zerado' => '#ef4444', 'pendente' => '#3b82f6', 'parcial' => '#f59e0b', 'conferido' => '#16a34a', 'divergente' => '#ef4444'];

    /**
     * @param  array{modo?: ?string, grupo?: ?string, busca?: ?string}  $opcoes
     * @return array{colunas: list<array<string, mixed>>, legenda: list<array{0: string, 1: string}>, resumo: array<string, int>, contagem: ?array<string, mixed>}
     */
    public function montar(string $unidadeId, array $opcoes = []): array
    {
        $modo = in_array($opcoes['modo'] ?? null, [self::MODO_SITUACAO, self::MODO_GRUPO, self::MODO_CONTAGEM], true) ? $opcoes['modo'] : self::MODO_SITUACAO;
        $grupo = $opcoes['grupo'] ?? null;
        $busca = mb_strtolower(trim((string) ($opcoes['busca'] ?? '')));

        $enderecos = StockLocation::query()->where('internal_unit_id', $unidadeId)->where('is_active', true)->orderBy('aisle')->orderBy('shelf')->orderBy('level')->get();
        $itens = app(EstoqueUnificado::class)->todos()->keyBy(fn ($l) => $l['tipo'].'|'.$l['id']);
        $placements = StockPlacement::query()->whereIn('stock_location_id', $enderecos->pluck('id'))->get()->groupBy('stock_location_id');

        $contagem = StockCount::query()->where('internal_unit_id', $unidadeId)->where('status', StockCount::EM_CONTAGEM)->latest()->first();
        $itensDaContagem = $contagem ? $contagem->items()->get()->groupBy('stock_location_id') : collect();

        $celulas = $enderecos->map(function (StockLocation $e) use ($placements, $itens, $itensDaContagem, $modo, $grupo, $busca) {
            $dentro = collect($placements->get($e->id, []))->filter(fn ($p) => (float) $p->quantity > 0)->map(function ($p) use ($itens) {
                $i = $itens->get($p->item_type.'|'.$p->item_id);

                return $i ? ['tipo' => $i['tipo'], 'id' => $i['id'], 'nome' => $i['nome'], 'sku' => $i['sku'], 'grupo' => $i['grupo'], 'grupo_id' => $i['grupo_id'], 'cor' => $i['grupo_cor'], 'quantidade' => (float) $p->quantity, 'unidade' => $i['unidade'], 'situacao' => $i['situacao'], 'valor' => (float) $p->quantity * $i['custo']] : null;
            })->filter()->values();

            $estado = match (true) {
                $dentro->isEmpty() => 'vazio',
                $dentro->contains('situacao', 'zerado') => 'zerado',
                $dentro->contains('situacao', 'abaixo') => 'abaixo',
                default => 'ok',
            };

            $cor = match ($modo) {
                self::MODO_GRUPO => $dentro->isEmpty() ? self::CORES['vazio'] : ($dentro->sortByDesc('valor')->first()['cor'] ?? self::CORES['ok']),
                self::MODO_CONTAGEM => self::CORES[$this->estadoDaContagem($itensDaContagem->get($e->id, collect()))],
                default => self::CORES[$estado],
            };

            $filtrada = $grupo !== null && $grupo !== '' || $busca !== '';
            $acende = ! $filtrada || (
                ($grupo === null || $grupo === '' || $dentro->contains(fn ($i) => $grupo === 'sem' ? $i['grupo_id'] === null : $i['grupo_id'] === $grupo))
                && ($busca === '' || $dentro->contains(fn ($i) => str_contains(mb_strtolower($i['nome'].' '.$i['sku']), $busca)))
            );

            return ['id' => (string) $e->id, 'codigo' => $e->code, 'corredor' => $e->aisle, 'prateleira' => $e->shelf, 'nivel' => $e->level ?? '1', 'estado' => $estado, 'cor' => $cor, 'itens' => $dentro->all(), 'destaque' => $acende, 'valor' => (float) $dentro->sum('valor')];
        });

        $colunas = $celulas->groupBy('corredor')->map(fn (Collection $doCorredor, $corredor) => [
            'corredor' => (string) $corredor,
            'prateleiras' => $doCorredor->groupBy('prateleira')->map(fn ($p, $prat) => ['prateleira' => (string) $prat, 'niveis' => $p->sortByDesc('nivel')->values()->all()])->values()->all(),
            'ocupados' => $doCorredor->where('estado', '!=', 'vazio')->count(), 'total' => $doCorredor->count(), 'valor' => (float) $doCorredor->sum('valor'),
        ])->values()->all();

        return [
            'colunas' => $colunas,
            'legenda' => $this->legenda($modo, $celulas),
            'resumo' => ['enderecos' => $celulas->count(), 'ocupados' => $celulas->where('estado', '!=', 'vazio')->count(), 'vazios' => $celulas->where('estado', 'vazio')->count(), 'com_problema' => $celulas->whereIn('estado', ['abaixo', 'zerado'])->count(), 'destacados' => $celulas->where('destaque', true)->count()],
            'contagem' => $contagem ? ['nome' => $contagem->name, 'id' => (string) $contagem->id] : null,
        ];
    }

    /** Detalhe de um endereco: itens, valor e a ultima contagem de cada item ali. @return array<string, mixed>|null */
    public function detalhe(string $enderecoId): ?array
    {
        $e = StockLocation::query()->with('internalUnit:id,name')->find($enderecoId);
        if (! $e) {
            return null;
        }
        $itens = app(EstoqueUnificado::class)->todos()->keyBy(fn ($l) => $l['tipo'].'|'.$l['id']);
        $linhas = StockPlacement::query()->where('stock_location_id', $e->id)->get()->map(function ($p) use ($itens, $e) {
            $i = $itens->get($p->item_type.'|'.$p->item_id);
            $ultima = StockCountItem::query()->where('stock_location_id', $e->id)->where('item_type', $p->item_type)->where('item_id', $p->item_id)->whereNotNull('counted_at')->orderByDesc('counted_at')->first(['counted_quantity', 'counted_by_name', 'counted_at']);

            return $i ? $i + ['no_endereco' => (float) $p->quantity, 'ultima_contagem' => $ultima] : null;
        })->filter()->sortBy('nome')->values();

        return ['id' => (string) $e->id, 'codigo' => $e->code, 'almoxarifado' => $e->internalUnit?->name, 'descricao' => $e->description, 'itens' => $linhas, 'valor' => (float) $linhas->sum(fn ($i) => $i['no_endereco'] * $i['custo'])];
    }

    /** @param  Collection<int, StockCountItem>  $itens */
    private function estadoDaContagem(Collection $itens): string
    {
        if ($itens->isEmpty()) {
            return 'vazio';
        }
        $contados = $itens->whereNotNull('counted_quantity');
        if ($contados->isEmpty()) {
            return 'pendente';
        }
        if ($contados->count() < $itens->count()) {
            return 'parcial';
        }

        return $contados->contains(fn ($i) => abs((float) $i->diferenca()) >= 0.001) ? 'divergente' : 'conferido';
    }

    /** @return list<array{0: string, 1: string}> */
    private function legenda(string $modo, Collection $celulas): array
    {
        return match ($modo) {
            self::MODO_GRUPO => $celulas->flatMap(fn ($c) => $c['itens'])->groupBy('grupo')->map(fn ($g, $nome) => [(string) $nome, (string) $g->first()['cor']])->values()->prepend(['Vazio', self::CORES['vazio']])->all(),
            self::MODO_CONTAGEM => [['Sem item na contagem', self::CORES['vazio']], ['A contar', self::CORES['pendente']], ['Contando', self::CORES['parcial']], ['Conferido', self::CORES['conferido']], ['Com divergência', self::CORES['divergente']]],
            default => [['Vazio', self::CORES['vazio']], ['Com itens', self::CORES['ok']], ['Item abaixo do mínimo', self::CORES['abaixo']], ['Item zerado', self::CORES['zerado']]],
        };
    }
}
