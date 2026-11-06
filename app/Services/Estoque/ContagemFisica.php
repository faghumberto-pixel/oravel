<?php

namespace App\Services\Estoque;

use App\Models\InternalUnit;
use App\Models\Material;
use App\Models\MaterialLocationStock;
use App\Models\Part;
use App\Models\StockCount;
use App\Models\StockCountEntry;
use App\Models\StockCountItem;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockPlacement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\MaterialStockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Contagem fisica (inventario) por endereco: monta a folha de contagem a partir de onde cada item esta e, ao finalizar,
 * grava o que foi contado como novo saldo do endereco e ajusta o estoque do sistema (materiais e pecas/oleo). Itens agregados
 * sao peca-a-peca: a divergencia fica registrada, mas o cadastro nao e' alterado automaticamente.
 */
class ContagemFisica
{
    /** Cria a contagem e a folha (uma linha por item em cada endereco do escopo). */
    public function criar(string $tenantId, string $unidadeId, string $nome, ?string $grupoId = null, ?string $corredor = null, ?string $userId = null): StockCount
    {
        return DB::transaction(function () use ($tenantId, $unidadeId, $nome, $grupoId, $corredor, $userId) {
            $contagem = StockCount::create([
                'tenant_id' => $tenantId, 'internal_unit_id' => $unidadeId, 'stock_group_id' => $grupoId, 'aisle' => $corredor,
                'name' => $nome, 'status' => StockCount::EM_CONTAGEM, 'created_by_user_id' => $userId,
            ]);

            $enderecos = StockLocation::query()->where('internal_unit_id', $unidadeId)->where('is_active', true)
                ->when($corredor, fn ($q) => $q->where('aisle', $corredor))->get()->keyBy('id');
            $itens = app(EstoqueUnificado::class)->todos()->keyBy(fn ($l) => $l['tipo'].'|'.$l['id']);

            $placements = StockPlacement::query()->whereIn('stock_location_id', $enderecos->keys())->get();
            foreach ($placements as $p) {
                $item = $itens->get($p->item_type.'|'.$p->item_id);
                if (! $item || ($grupoId && $item['grupo_id'] !== $grupoId)) {
                    continue;
                }
                StockCountItem::create([
                    'tenant_id' => $tenantId, 'stock_count_id' => $contagem->id, 'stock_location_id' => $p->stock_location_id,
                    'location_code' => $enderecos[$p->stock_location_id]->code, 'item_type' => $p->item_type, 'item_id' => $p->item_id,
                    'item_name' => $item['nome'], 'item_sku' => $item['sku'], 'group_name' => $item['grupo'], 'expected_quantity' => $p->quantity,
                ]);
            }

            return $contagem;
        });
    }

    /** Registra uma contagem (ou recontagem) de um item: guarda quem, quando e quanto, e atualiza o "contado" da folha. */
    public function registrar(StockCountItem $item, float $quantidade, ?User $usuario = null, string $origem = 'web', ?string $nota = null, ?Carbon $quando = null): StockCountEntry
    {
        if (! $item->count->aberta()) {
            throw new RuntimeException('Esta contagem já foi finalizada: não aceita novos registros.');
        }
        if ($quantidade < 0) {
            throw new RuntimeException('A quantidade contada não pode ser negativa.');
        }

        return DB::transaction(function () use ($item, $quantidade, $usuario, $origem, $nota, $quando) {
            $agora = $quando ?? now();
            $entrada = StockCountEntry::create([
                'tenant_id' => $item->tenant_id, 'stock_count_item_id' => $item->id, 'quantity' => $quantidade, 'counted_by_user_id' => $usuario?->id,
                'counted_by_name' => $usuario?->name, 'source' => $origem, 'note' => $nota, 'counted_at' => $agora,
            ]);
            $item->update(['counted_quantity' => $quantidade, 'counted_at' => $agora, 'counted_by_user_id' => $usuario?->id, 'counted_by_name' => $usuario?->name, 'note' => $nota ?? $item->note]);

            return $entrada;
        });
    }

    /**
     * Finaliza a contagem: ajusta o estoque pelas divergencias e atualiza a quantidade de cada endereco contado.
     *
     * @return array{ajustados: int, divergentes: int, sem_contagem: int, informativos: int}
     */
    public function finalizar(StockCount $contagem, ?string $userId = null): array
    {
        if (! $contagem->aberta()) {
            throw new RuntimeException('Esta contagem já foi finalizada.');
        }

        // Os ajustes de estoque desta finalizacao nao mexem nos enderecos (a propria contagem grava o contado em cada um).
        return EstoqueEnderecos::pausar(fn () => DB::transaction(function () use ($contagem, $userId) {
            $resumo = ['ajustados' => 0, 'divergentes' => 0, 'sem_contagem' => 0, 'informativos' => 0];
            $unidade = InternalUnit::findOrFail($contagem->internal_unit_id);

            foreach ($contagem->items()->get() as $item) {
                if ($item->counted_quantity === null) {
                    $resumo['sem_contagem']++;

                    continue;
                }

                $dif = $item->diferenca();
                if (abs((float) $dif) >= 0.001) {
                    $resumo['divergentes']++;
                    match ($item->item_type) {
                        StockPlacement::MATERIAL => $resumo['ajustados'] += $this->ajustarMaterial($item, $unidade, (float) $dif, $userId, $contagem) ? 1 : 0,
                        StockPlacement::PART => $resumo['ajustados'] += $this->ajustarPeca($item, (float) $dif, $userId, $contagem) ? 1 : 0,
                        default => $resumo['informativos']++,
                    };
                }

                StockPlacement::query()->updateOrCreate(
                    ['stock_location_id' => $item->stock_location_id, 'item_type' => $item->item_type, 'item_id' => $item->item_id],
                    ['tenant_id' => $item->tenant_id, 'quantity' => $item->counted_quantity],
                );
            }

            $contagem->update(['status' => StockCount::FINALIZADA, 'finished_at' => now()]);

            return $resumo;
        }));
    }

    private function ajustarMaterial(StockCountItem $item, InternalUnit $unidade, float $dif, ?string $userId, StockCount $contagem): bool
    {
        $material = Material::find($item->item_id);
        if (! $material) {
            return false;
        }
        $atual = MaterialLocationStock::query()->where('material_id', $material->id)->where('internal_unit_id', $unidade->id)->value('current_quantity') ?? $material->current_stock;
        app(MaterialStockService::class)->adjust($material, $unidade, max(0.0, (float) $atual + $dif), $userId, $contagem);

        return true;
    }

    private function ajustarPeca(StockCountItem $item, float $dif, ?string $userId, StockCount $contagem): bool
    {
        $peca = Part::find($item->item_id);
        $deposito = Warehouse::query()->where('is_active', true)->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'mobile'))->orderBy('id')->first();
        if (! $peca || ! $deposito) {
            return false;
        }

        $estoque = WarehouseStock::query()->firstOrCreate(['warehouse_id' => $deposito->id, 'part_id' => $peca->id], ['current_quantity' => 0, 'reserved_quantity' => 0]);
        $antes = (float) $estoque->current_quantity;
        $depois = max(0.0, $antes + $dif);
        $estoque->update(['current_quantity' => $depois]);

        StockMovement::create([
            'tenant_id' => $peca->tenant_id, 'part_id' => $peca->id, 'warehouse_id' => $deposito->id,
            'movement_type' => $dif > 0 ? 'entry_adjustment' : 'exit_adjustment', 'quantity' => abs($dif), 'balance_before' => $antes, 'balance_after' => $depois,
            'unit_cost' => (float) $peca->cost_price, 'total_cost' => round(abs($dif) * (float) $peca->cost_price, 2),
            'reference_document' => 'Contagem física: '.$contagem->name, 'notes' => 'Ajuste por contagem física no endereço '.$item->location_code, 'created_by' => $userId,
        ]);

        return true;
    }
}
