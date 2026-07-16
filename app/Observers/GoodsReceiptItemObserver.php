<?php

namespace App\Observers;

use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrderItem;
use App\Services\MaterialStockService;
use Illuminate\Support\Facades\DB;

/**
 * Unico ponto de entrada de estoque a partir de uma compra -- ao
 * registrar uma linha de recebimento: valida numero de serie obrigatorio
 * (mesma trava de MaintenanceOrderMaterial::booted()), da entrada na
 * filial do recebimento via MaterialStockService (que grava o ledger e
 * recalcula Material.current_stock sozinho), soma em
 * PurchaseOrderItem.quantity_received e recalcula PurchaseOrder.status.
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
            $unit = $item->goodsReceipt?->internalUnit;

            if (! $material || ! $unit) {
                return;
            }

            app(MaterialStockService::class)->receive(
                $material,
                $unit,
                (float) $item->quantity_received,
                $item,
                $item->goodsReceipt?->received_by_user_id,
                $item->goodsReceipt?->invoice_number,
            );

            $purchaseOrderItem->increment('quantity_received', (float) $item->quantity_received);
            $purchaseOrderItem->purchaseOrder->recalculateStatus();
        });
    }
}
