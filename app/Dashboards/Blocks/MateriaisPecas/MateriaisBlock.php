<?php

namespace App\Dashboards\Blocks\MateriaisPecas;

use App\Dashboards\Block;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\PurchaseOrder;
use Illuminate\Support\Collection;

/** Base dos blocos de Materiais e Peças: exige o módulo Materiais (cada bloco pode trocar para Compras, Requisições etc.). */
abstract class MateriaisBlock extends Block
{
    public static function module(): string
    {
        return 'Materiais e Peças';
    }

    public static function requiredFeature(): string
    {
        return 'tabela_materials';
    }

    public static function sourceModel(): string
    {
        return Material::class;
    }

    protected function brl(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }

    protected function mil(float $valor): string
    {
        return 'R$ '.number_format($valor / 1000, 1, ',', '.').' mil';
    }

    /** Pedidos de compra que ainda esperam entrega. */
    protected function situacoesEmAberto(): array
    {
        return [PurchaseOrder::STATUS_ABERTA, PurchaseOrder::STATUS_PARCIALMENTE_RECEBIDA];
    }

    /**
     * Consumo (saídas) por material no período, em unidades.
     *
     * @return Collection<string, float> material_id => quantidade
     */
    protected function saidasPorMaterial(int $dias): Collection
    {
        return MaterialStockMovement::query()
            ->where('type', MaterialStockMovement::TYPE_SAIDA)
            ->where('created_at', '>=', now()->subDays($dias))
            ->selectRaw('material_id, sum(quantity) as total')
            ->groupBy('material_id')
            ->pluck('total', 'material_id')
            ->map(fn ($q) => (float) $q);
    }
}
