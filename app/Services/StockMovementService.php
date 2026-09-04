<?php

namespace App\Services;

use App\Models\Part;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;
use Throwable;

class StockMovementService
{
    /**
     * Registra uma entrada de estoque (compra, ajuste, devolução).
     * Utiliza transação pessimista para evitar race conditions.
     *
     * @param int $warehouseId ID do almoxarifado
     * @param int $partId ID da peça
     * @param float $quantity Quantidade a adicionar
     * @param float $unitCost Custo unitário da entrada
     * @param string $type Tipo de movimento (entry_purchase, entry_adjustment, entry_return)
     * @param string|null $referenceDocument Número de NF, ID de documento, etc
     * @param string $notes Observações
     * @param int|null $userId ID do usuário responsável
     * @return StockMovement
     */
    public function recordEntry(
        int $warehouseId,
        int $partId,
        float $quantity,
        float $unitCost,
        string $type,
        ?string $referenceDocument = null,
        string $notes = '',
        ?int $userId = null
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantidade deve ser positiva');
        }

        $userId = $userId ?? auth()->id();
        $tenantId = auth()->user()?->tenant_id;

        if (!$tenantId) {
            throw new \Exception('Tenant ID não encontrado');
        }

        return DB::transaction(function () use (
            $warehouseId,
            $partId,
            $quantity,
            $unitCost,
            $type,
            $referenceDocument,
            $notes,
            $userId,
            $tenantId
        ) {
            // Validações
            Warehouse::where('id', $warehouseId)->where('tenant_id', $tenantId)->firstOrFail();
            $part = Part::where('id', $partId)->where('tenant_id', $tenantId)->firstOrFail();

            // Lock pessimista no registro de estoque
            $stock = WarehouseStock::where('warehouse_id', $warehouseId)
                ->where('part_id', $partId)
                ->lockForUpdate()
                ->firstOrCreate(
                    ['warehouse_id' => $warehouseId, 'part_id' => $partId],
                    ['current_quantity' => 0, 'reserved_quantity' => 0]
                );

            // Calcula novo custo médio ponderado
            $currentQty = (float)$stock->current_quantity;
            $currentCost = (float)$part->cost_price;
            $newQty = $currentQty + $quantity;

            if ($newQty > 0) {
                $newAverageCost = (($currentQty * $currentCost) + ($quantity * $unitCost)) / $newQty;
                $part->update(['cost_price' => round($newAverageCost, 4)]);
            }

            // Atualiza saldo
            $balanceBefore = $currentQty;
            $balanceAfter = $newQty;
            $totalCost = $quantity * $unitCost;

            $stock->update(['current_quantity' => $balanceAfter]);

            // Registra movimento no Kardex (imutável)
            return StockMovement::create([
                'tenant_id' => $tenantId,
                'part_id' => $partId,
                'warehouse_id' => $warehouseId,
                'movement_type' => $type,
                'quantity' => $quantity,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'reference_document' => $referenceDocument,
                'notes' => $notes,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Registra uma saída de estoque (ordem de serviço, ajuste, perda).
     * Valida saldo suficiente antes de decrementar.
     *
     * @param int $warehouseId ID do almoxarifado
     * @param int $partId ID da peça
     * @param float $quantity Quantidade a remover
     * @param string $type Tipo de movimento (exit_work_order, exit_adjustment, exit_loss)
     * @param string|null $referenceDocument ID da OS, etc
     * @param string $notes Observações
     * @param int|null $userId ID do usuário responsável
     * @param bool $allowNegative Permitir estoque negativo (default: false)
     * @return StockMovement
     */
    public function recordExit(
        int $warehouseId,
        int $partId,
        float $quantity,
        string $type,
        ?string $referenceDocument = null,
        string $notes = '',
        ?int $userId = null,
        bool $allowNegative = false
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantidade deve ser positiva');
        }

        $userId = $userId ?? auth()->id();
        $tenantId = auth()->user()?->tenant_id;

        if (!$tenantId) {
            throw new \Exception('Tenant ID não encontrado');
        }

        return DB::transaction(function () use (
            $warehouseId,
            $partId,
            $quantity,
            $type,
            $referenceDocument,
            $notes,
            $userId,
            $tenantId,
            $allowNegative
        ) {
            // Validações
            Warehouse::where('id', $warehouseId)->where('tenant_id', $tenantId)->firstOrFail();
            $part = Part::where('id', $partId)->where('tenant_id', $tenantId)->firstOrFail();

            // Lock pessimista
            $stock = WarehouseStock::where('warehouse_id', $warehouseId)
                ->where('part_id', $partId)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                throw new \Exception('Estoque não encontrado para esta peça neste almoxarifado');
            }

            $currentQty = (float)$stock->current_quantity;

            // Valida saldo
            if (!$allowNegative && $currentQty < $quantity) {
                throw new \Exception(
                    "Saldo insuficiente. Disponível: {$currentQty} {$part->unit_of_measure}, solicitado: {$quantity}"
                );
            }

            // Atualiza saldo
            $balanceBefore = $currentQty;
            $balanceAfter = $currentQty - $quantity;
            $unitCost = (float)$part->cost_price;
            $totalCost = $quantity * $unitCost;

            $stock->update(['current_quantity' => $balanceAfter]);

            // Registra movimento
            return StockMovement::create([
                'tenant_id' => $tenantId,
                'part_id' => $partId,
                'warehouse_id' => $warehouseId,
                'movement_type' => $type,
                'quantity' => $quantity,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'reference_document' => $referenceDocument,
                'notes' => $notes,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Transfere estoque entre almoxarifados.
     *
     * @param int $fromWarehouseId Almoxarifado de origem
     * @param int $toWarehouseId Almoxarifado de destino
     * @param int $partId ID da peça
     * @param float $quantity Quantidade a transferir
     * @param string $notes Observações
     * @param int|null $userId ID do usuário responsável
     * @return array ['exit_movement' => StockMovement, 'entry_movement' => StockMovement]
     */
    public function transferBetweenWarehouses(
        int $fromWarehouseId,
        int $toWarehouseId,
        int $partId,
        float $quantity,
        string $notes = '',
        ?int $userId = null
    ): array {
        $userId = $userId ?? auth()->id();

        // Saída do almoxarifado de origem
        $exitMovement = $this->recordExit(
            $fromWarehouseId,
            $partId,
            $quantity,
            'transfer_out',
            null,
            $notes,
            $userId,
            false
        );

        // Entrada no almoxarifado de destino
        $part = Part::findOrFail($partId);
        $entryMovement = $this->recordEntry(
            $toWarehouseId,
            $partId,
            $quantity,
            (float)$part->cost_price,
            'transfer_in',
            null,
            $notes,
            $userId
        );

        return [
            'exit_movement' => $exitMovement,
            'entry_movement' => $entryMovement,
        ];
    }
}
