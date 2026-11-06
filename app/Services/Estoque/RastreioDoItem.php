<?php

namespace App\Services\Estoque;

use App\Models\AggregateItem;
use App\Models\AggregateItemEntry;
use App\Models\AggregateItemExit;
use App\Models\AggregateItemType;
use App\Models\FrotaAbastecimento;
use App\Models\FrotaTrocaOleo;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderMaterial;
use App\Models\MaintenanceOrderPart;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\Part;
use App\Models\StockCountItem;
use App\Models\StockMovement;
use App\Models\StockPlacement;
use App\Models\User;
use App\Models\WarehouseStock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rastreio de UM item: tudo que aconteceu com ele, em ordem (entradas, saidas, ajustes, transferencias, uso em ordens de servico
 * e na frota, contagens fisicas). Funciona para material, peca/oleo, tipo de item agregado e cada unidade de item agregado.
 */
class RastreioDoItem
{
    public const UNIDADE_AGREGADA = 'aggregate_unit';

    /**
     * @return array{nome: string, sku: ?string, tipo_rotulo: string, detalhes: array<string, string>}|null
     */
    public function cabecalho(string $tipo, string $id): ?array
    {
        return match ($tipo) {
            StockPlacement::MATERIAL => ($m = Material::with(['category:id,name', 'stockGroup:id,name'])->find($id)) ? [
                'nome' => $m->name, 'sku' => $m->sku, 'tipo_rotulo' => 'Material',
                'detalhes' => ['Grupo' => $m->stockGroup?->name ?? 'Sem grupo', 'Categoria' => $m->category?->name ?? 'Sem categoria', 'Saldo atual' => $this->num($m->current_stock).' '.($m->unit_of_measure ?: 'un'), 'Mínimo' => (string) $m->min_stock],
            ] : null,
            StockPlacement::PART => ($p = Part::with(['category:id,name', 'stockGroup:id,name'])->find($id)) ? [
                'nome' => $p->name, 'sku' => $p->sku, 'tipo_rotulo' => 'Peça / óleo',
                'detalhes' => ['Grupo' => $p->stockGroup?->name ?? 'Sem grupo', 'Categoria' => $p->category?->name ?? 'Sem categoria', 'Saldo atual' => $this->num(WarehouseStock::where('part_id', $p->id)->sum('current_quantity')).' '.($p->unit_of_measure ?: 'un'), 'Mínimo' => $this->num($p->minimum_stock)],
            ] : null,
            StockPlacement::AGGREGATE => ($t = AggregateItemType::with('stockGroup:id,name')->find($id)) ? [
                'nome' => $t->name, 'sku' => null, 'tipo_rotulo' => 'Item agregado (tipo)',
                'detalhes' => ['Grupo' => $t->stockGroup?->name ?? 'Sem grupo', 'Categoria' => ucfirst((string) $t->category), 'Unidades cadastradas' => (string) AggregateItem::where('aggregate_item_type_id', $t->id)->count()],
            ] : null,
            self::UNIDADE_AGREGADA => ($u = AggregateItem::with('type:id,name')->find($id)) ? [
                'nome' => ($u->type?->name ?? 'Item agregado').' · '.$u->code, 'sku' => $u->code, 'tipo_rotulo' => 'Item agregado (unidade)',
                'detalhes' => ['Situação' => (string) $u->status, 'Série' => (string) ($u->serial_number ?: '—'), 'Comprado em' => $u->purchase_date?->format('d/m/Y') ?? '—'],
            ] : null,
            default => null,
        };
    }

    /**
     * Linhas do tempo do item, da mais recente para a mais antiga.
     *
     * @return Collection<int, array{quando: Carbon, tipo: string, titulo: string, detalhe: string, quantidade: ?float, saldo: ?float, quem: ?string, tom: string}>
     */
    public function eventos(string $tipo, string $id): Collection
    {
        $eventos = match ($tipo) {
            StockPlacement::MATERIAL => $this->material($id),
            StockPlacement::PART => $this->peca($id),
            StockPlacement::AGGREGATE => $this->agregado($id),
            self::UNIDADE_AGREGADA => $this->unidadeAgregada($id),
            default => collect(),
        };

        $tipoContagem = $tipo === self::UNIDADE_AGREGADA ? StockPlacement::AGGREGATE : $tipo;
        $idContagem = $tipo === self::UNIDADE_AGREGADA ? (string) AggregateItem::whereKey($id)->value('aggregate_item_type_id') : $id;

        return $eventos->concat($this->contagens($tipoContagem, $idContagem))->sortByDesc(fn ($e) => $e['quando']->timestamp)->values();
    }

    /** Resumo do que entrou, saiu e foi usado. @return array{entradas: float, saidas: float, ajustes: float, usos: int, contagens: int} */
    public function totais(Collection $eventos): array
    {
        return [
            'entradas' => (float) $eventos->where('tipo', 'entrada')->sum('quantidade'),
            'saidas' => abs((float) $eventos->where('tipo', 'saida')->sum('quantidade')),
            'ajustes' => (float) $eventos->where('tipo', 'ajuste')->sum('quantidade'),
            'usos' => $eventos->where('tipo', 'uso')->count(),
            'contagens' => $eventos->where('tipo', 'contagem')->count(),
        ];
    }

    /** Busca itens (os tres cadastros e cada unidade de item agregado) para o seletor. @return array<string, string> "tipo|id" => rotulo */
    public function buscar(string $termo = '', int $limite = 40): array
    {
        $like = '%'.mb_strtolower(trim($termo)).'%';
        $filtra = fn ($q, array $colunas) => $termo === '' ? $q : $q->where(function ($w) use ($colunas, $like) {
            foreach ($colunas as $c) {
                $w->orWhereRaw('lower('.$c.') like ?', [$like]);
            }
        });

        $opcoes = [];
        foreach ($filtra(Material::query(), ['name', 'sku'])->orderBy('name')->limit($limite)->get(['id', 'name', 'sku']) as $m) {
            $opcoes[StockPlacement::MATERIAL.'|'.$m->id] = 'Material · '.$m->name.' ('.$m->sku.')';
        }
        foreach ($filtra(Part::query(), ['name', 'sku'])->orderBy('name')->limit($limite)->get(['id', 'name', 'sku']) as $p) {
            $opcoes[StockPlacement::PART.'|'.$p->id] = 'Peça/óleo · '.$p->name.' ('.$p->sku.')';
        }
        foreach ($filtra(AggregateItemType::query(), ['name'])->orderBy('name')->limit($limite)->get(['id', 'name']) as $t) {
            $opcoes[StockPlacement::AGGREGATE.'|'.$t->id] = 'Item agregado · '.$t->name.' (todas as unidades)';
        }
        foreach ($filtra(AggregateItem::query()->with('type:id,name'), ['code', 'serial_number'])->orderBy('code')->limit($limite)->get(['id', 'code', 'aggregate_item_type_id']) as $u) {
            $opcoes[self::UNIDADE_AGREGADA.'|'.$u->id] = 'Unidade · '.($u->type?->name ?? 'Item').' · '.$u->code;
        }

        return $opcoes;
    }

    private function num(float|int|string|null $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',') ?: '0';
    }

    private function nomeDoUsuario(?string $id, array &$cache): ?string
    {
        if (! $id) {
            return null;
        }

        return $cache[$id] ??= User::withoutGlobalScopes()->whereKey($id)->value('name');
    }

    private function evento(Carbon $quando, string $tipo, string $titulo, string $detalhe, ?float $quantidade, ?float $saldo, ?string $quem, string $tom): array
    {
        return compact('quando', 'tipo', 'titulo', 'detalhe', 'quantidade', 'saldo', 'quem', 'tom');
    }

    private function os(?string $id): string
    {
        if (! $id) {
            return '';
        }
        $os = MaintenanceOrder::withoutGlobalScopes()->whereKey($id)->first(['os_number']);

        return $os ? 'OS '.($os->os_number ?: substr($id, 0, 6)) : '';
    }

    private function material(string $id): Collection
    {
        $nomes = [];
        $eventos = collect();

        foreach (MaterialStockMovement::query()->where('material_id', $id)->get() as $m) {
            $tipo = (string) $m->type;
            $q = (float) $m->quantity;
            $ref = $m->reference_type === MaintenanceOrder::class ? $this->os($m->reference_id) : ($m->document_reference ?: '');
            $eventos->push($this->evento(
                Carbon::parse($m->created_at), $tipo, match ($tipo) {
                    'entrada' => 'Entrada no estoque', 'saida' => 'Saída do estoque', default => 'Ajuste de estoque',
                },
                trim(($m->reason ?: '').($ref ? ' · '.$ref : ''), ' ·') ?: 'sem detalhe', $tipo === 'saida' ? -abs($q) : $q, (float) $m->balance_after,
                $this->nomeDoUsuario($m->created_by_user_id, $nomes), match ($tipo) {
                    'entrada' => 'ok', 'saida' => 'warn', default => 'a',
                },
            ));
        }

        foreach (MaintenanceOrderMaterial::query()->where('material_id', $id)->with('maintenanceOrder:id,os_number,technician_id')->get() as $u) {
            $os = $u->maintenanceOrder;
            $eventos->push($this->evento(
                Carbon::parse($u->created_at), 'uso', 'Usado em ordem de serviço',
                ($os ? 'OS '.($os->os_number ?: substr($os->id, 0, 6)) : 'OS').($u->serial_number ? ' · série '.$u->serial_number : '').' · '.$this->num($u->quantity).' un'.((float) $u->unit_price > 0 ? ' · R$ '.number_format((float) $u->unit_price, 2, ',', '.').' cada' : ''),
                -abs((float) $u->quantity), null, $os ? $this->nomeDoUsuario($os->technician_id, $nomes) : null, 'n',
            ));
        }

        return $eventos;
    }

    private function peca(string $id): Collection
    {
        $nomes = [];
        $eventos = collect();
        $movimentos = StockMovement::query()->where('part_id', $id)->get();
        $trocas = FrotaTrocaOleo::query()->where('peca_id', $id)->with('veiculo:id,name,placa')->get();
        $abastecimentos = FrotaAbastecimento::query()->where('peca_id', $id)->get();

        // Uso na frota que ja baixou o estoque (saida "exit_fleet" de mesma quantidade, na mesma hora) vira UM evento so':
        // o uso, com o saldo e a pessoa do movimento. Sem movimento (ex.: registro antigo), o uso aparece sozinho.
        $casados = [];
        $casar = function (float $quantidade, $quando) use ($movimentos, &$casados) {
            $m = $movimentos->first(fn ($m) => $m->movement_type === 'exit_fleet' && ! isset($casados[$m->id]) && abs((float) $m->quantity - $quantidade) < 0.001 && abs(Carbon::parse($m->created_at)->diffInMinutes(Carbon::parse($quando), false)) <= 10);
            if ($m) {
                $casados[$m->id] = true;
            }

            return $m;
        };

        foreach ($trocas as $t) {
            $m = $casar((float) $t->litros, $t->realizado_em);
            $eventos->push($this->evento(Carbon::parse($t->realizado_em), 'uso', 'Usado na frota — troca de óleo', ($t->veiculo?->name ?? 'Veículo').' ('.($t->veiculo?->placa ?? 'sem placa').') · '.$t->litros.' L · '.$t->produto, -abs((float) $t->litros), $m ? (float) $m->balance_after : null, $this->nomeDoUsuario($m?->created_by ?? $t->realizado_por, $nomes), 'n'));
        }
        foreach ($abastecimentos as $a) {
            $m = $casar((float) $a->litros, $a->abastecido_em);
            $eventos->push($this->evento(Carbon::parse($a->abastecido_em), 'uso', 'Usado na frota — abastecimento', $this->num($a->litros).' L · odômetro '.$a->odometro, -abs((float) $a->litros), $m ? (float) $m->balance_after : null, $this->nomeDoUsuario($m?->created_by ?? $a->registrado_por, $nomes), 'n'));
        }

        foreach ($movimentos as $m) {
            if (isset($casados[$m->id])) {
                continue;
            }
            $entrada = str_starts_with((string) $m->movement_type, 'entry_') || $m->movement_type === 'transfer_in';
            $tipo = str_contains((string) $m->movement_type, 'adjustment') ? 'ajuste' : ($entrada ? 'entrada' : 'saida');
            $eventos->push($this->evento(
                Carbon::parse($m->created_at), $tipo, StockMovement::MOVEMENT_TYPES[$m->movement_type] ?? $m->movement_type,
                trim(($m->reference_document ?: '').($m->work_order_id ? ' · '.$this->os($m->work_order_id) : '').($m->notes ? ' · '.$m->notes : ''), ' ·') ?: 'sem detalhe',
                $entrada ? (float) $m->quantity : -(float) $m->quantity, (float) $m->balance_after, $this->nomeDoUsuario($m->created_by, $nomes), match ($tipo) {
                    'entrada' => 'ok', 'saida' => 'warn', default => 'a',
                },
            ));
        }

        foreach (MaintenanceOrderPart::query()->where('part_id', $id)->get() as $u) {
            $eventos->push($this->evento(Carbon::parse($u->created_at), 'uso', 'Usada em ordem de serviço', $this->os($u->maintenance_order_id).' · '.$this->num($u->quantity).' un', -abs((float) $u->quantity), null, null, 'n'));
        }

        return $eventos;
    }

    private function agregado(string $tipoId): Collection
    {
        $eventos = collect();
        foreach (AggregateItemEntry::query()->where('aggregate_item_type_id', $tipoId)->get() as $e) {
            $eventos->push($this->evento(Carbon::parse($e->entry_date), 'entrada', 'Entrada de unidades', ($e->invoice_number ? 'NF '.$e->invoice_number.' · ' : '').$e->quantity.' un'.($e->unit_price ? ' · R$ '.number_format((float) $e->unit_price, 2, ',', '.').' cada' : ''), (float) $e->quantity, null, null, 'ok'));
        }
        foreach (AggregateItemExit::query()->where('aggregate_item_type_id', $tipoId)->with(['asset:id,name', 'maintenanceOrder:id,os_number'])->get() as $s) {
            $eventos->push($this->saidaAgregada($s));
        }

        return $eventos;
    }

    private function unidadeAgregada(string $id): Collection
    {
        $u = AggregateItem::find($id);
        if (! $u) {
            return collect();
        }
        $eventos = collect();
        if ($u->purchase_date) {
            $eventos->push($this->evento(Carbon::parse($u->purchase_date), 'entrada', 'Compra / entrada da unidade', ($u->invoice_number ? 'NF '.$u->invoice_number.' · ' : '').($u->purchase_value ? 'R$ '.number_format((float) $u->purchase_value, 2, ',', '.') : 'sem valor informado'), 1.0, null, null, 'ok'));
        }
        foreach (AggregateItemExit::query()->where('aggregate_item_id', $id)->with(['asset:id,name', 'maintenanceOrder:id,os_number'])->get() as $s) {
            $eventos->push($this->saidaAgregada($s));
        }

        return $eventos;
    }

    private function saidaAgregada(AggregateItemExit $s): array
    {
        $destino = $s->asset?->name ? 'para '.$s->asset->name : ($s->maintenanceOrder ? 'OS '.($s->maintenanceOrder->os_number ?: '') : '');
        $volta = $s->returned ? 'devolvido em '.($s->returned_at?->format('d/m/Y') ?? '—').($s->returned_condition ? ' ('.$s->returned_condition.')' : '') : 'ainda fora';

        return $this->evento(Carbon::parse($s->exit_date), 'uso', 'Saída / uso do item', trim(($s->reason ?: '').' '.$destino.' · '.$volta, ' ·'), -abs((float) $s->quantity), null, null, $s->returned ? 'ok' : 'warn');
    }

    /** Registros de contagem fisica do item (de qualquer contagem). */
    private function contagens(string $tipo, string $id): Collection
    {
        return StockCountItem::query()->where('item_type', $tipo)->where('item_id', $id)->with(['entries', 'count:id,name'])->get()
            ->flatMap(fn ($item) => $item->entries->map(fn ($e) => $this->evento(
                Carbon::parse($e->counted_at), 'contagem', 'Contagem física — '.($item->count?->name ?? ''),
                $item->location_code.' · contou '.$this->num($e->quantity).' (esperado '.$this->num($item->expected_quantity).')'.($e->note ? ' · '.$e->note : '').' · '.($e->source === 'celular' ? 'pelo celular' : 'pelo computador'),
                (float) $e->quantity, null, $e->counted_by_name, abs((float) $e->quantity - (float) $item->expected_quantity) < 0.001 ? 'ok' : 'crit',
            )));
    }
}
