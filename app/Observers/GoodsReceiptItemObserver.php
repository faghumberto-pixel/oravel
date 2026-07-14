<?php

namespace App\Observers;

use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Unico ponto de entrada de estoque a partir de uma compra -- ao
 * registrar uma linha de recebimento: valida numero de serie obrigatorio
 * (mesma trava de MaintenanceOrderMaterial::booted()), incrementa
 * Material.current_stock, grava MaterialStockMovement tipo entrada_compra,
 * soma em PurchaseOrderItem.quantity_received e recalcula
 * PurchaseOrder.status.
 */
class GoodsReceiptItemObserver
{
    public function creating(GoodsReceiptItem $item): void
    {
        $purchaseOrderItem = $item->purchaseOrderItem ?? PurchaseOrderItem::find($item->purchase_order_item_id);
        $material = $purchaseOrderItem?->material;

        if ($material?->requires_serial_number && blank($item->serial_number)) {
            throw new \InvalidArgumentException(
                "O material \"{$material->name}\" exige número de série antes de receber."
            );
        }
    }

    public function created(GoodsReceiptItem $item): void
    {
        DB::transaction(function () use ($item) {
            $purchaseOrderItem = $item->purchaseOrderItem;
            $material = $purchaseOrderItem->material;

            if (! $material) {
                return;
            }

            // Material.current_stock e' coluna integer (mesmo motivo ja
            // documentado em MaterialStockTake::finalize()) -- arredonda
            // em vez de truncar.
            $novoSaldo = (int) round((float) $material->current_stock + (float) $item->quantity_received);
            $material->update(['current_stock' => $novoSaldo]);

            StockMovement::record(
                $material,
                StockMovement::TYPE_ENTRADA_COMPRA,
                (float) $item->quantity_received,
                (float) $novoSaldo,
                $item,
                $item->goodsReceipt?->received_by_user_id
            );

            $purchaseOrderItem->increment('quantity_received', (float) $item->quantity_received);
            $purchaseOrderItem->purchaseOrder->recalculateStatus();
        });
    }
}
