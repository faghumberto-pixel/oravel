<?php

namespace App\Services;

use App\Models\Part;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentService
{
    public function __construct(
        private StockMovementService $stockMovementService
    ) {}

    /**
     * Ajusta o saldo de uma peça em um almoxarifado após contagem física.
     *
     * Calcula a diferença entre o saldo atual do sistema e a quantidade contada,
     * registrando um movimento de ajuste apropriado.
     *
     * @param int $warehouseId ID do almoxarifado
     * @param int $partId ID da peça
     * @param float $countedQuantity Quantidade contada fisicamente
     * @param string $reason Motivo do ajuste (ex: "Contagem de inventário mensal", "Quebra descoberta")
     * @param int|null $userId ID do usuário responsável
     * @return StockMovement Movimento de ajuste criado
     */
    public function adjust(
        int $warehouseId,
        int $partId,
        float $countedQuantity,
        string $reason,
        ?int $userId = null
    ): StockMovement {
        if (!$reason) {
            throw new \InvalidArgumentException('Razão do ajuste é obrigatória');
        }

        $userId = $userId ?? auth()->id();
        $tenantId = auth()->user()?->tenant_id;

        return DB::transaction(function () use (
            $warehouseId,
            $partId,
            $countedQuantity,
            $reason,
            $userId,
            $tenantId
        ) {
            // Valida almoxarifado e peça
            Warehouse::where('id', $warehouseId)->where('tenant_id', $tenantId)->firstOrFail();
            Part::where('id', $partId)->where('tenant_id', $tenantId)->firstOrFail();

            // Obtém saldo atual com lock
            $stock = WarehouseStock::where('warehouse_id', $warehouseId)
                ->where('part_id', $partId)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                throw new \Exception('Estoque não encontrado');
            }

            $currentSystemQuantity = (float)$stock->current_quantity;
            $difference = $countedQuantity - $currentSystemQuantity;

            // Se não há diferença, não faz nada
            if ($difference == 0) {
                throw new \Exception('Quantidade contada é igual ao saldo do sistema. Nenhum ajuste necessário.');
            }

            // Registra ajuste (entrada ou saída)
            if ($difference > 0) {
                // Sobra - entrada de ajuste
                return $this->stockMovementService->recordEntry(
                    $warehouseId,
                    $partId,
                    $difference,
                    0, // Custo zero para ajuste
                    'entry_adjustment',
                    null,
                    "Contagem física: {$currentSystemQuantity} → {$countedQuantity} ({$reason})",
                    $userId
                );
            } else {
                // Falta - saída de ajuste
                return $this->stockMovementService->recordExit(
                    $warehouseId,
                    $partId,
                    abs($difference),
                    'exit_adjustment',
                    null,
                    "Contagem física: {$currentSystemQuantity} → {$countedQuantity} ({$reason})",
                    $userId,
                    true // Permite negativo para ajuste
                );
            }
        });
    }

    /**
     * Realiza ciclo completo de inventário (contagem de múltiplas peças).
     * Retorna resumo de ajustes e discrepâncias.
     *
     * @param int $warehouseId ID do almoxarifado
     * @param array $countedItems Array de ['part_id' => int, 'quantity' => float]
     * @param string $inventoryReason Motivo do inventário (ex: "Inventário mensal")
     * @param int|null $userId ID do usuário responsável
     * @return array Resumo de ajustes realizados
     */
    public function inventoryCount(
        int $warehouseId,
        array $countedItems,
        string $inventoryReason,
        ?int $userId = null
    ): array {
        $userId = $userId ?? auth()->id();
        $adjustments = [];

        foreach ($countedItems as $item) {
            try {
                $adjustment = $this->adjust(
                    $warehouseId,
                    $item['part_id'],
                    $item['quantity'],
                    $inventoryReason,
                    $userId
                );

                $adjustments[] = [
                    'status' => 'success',
                    'part_id' => $item['part_id'],
                    'movement_id' => $adjustment->id,
                    'difference' => $adjustment->balance_after - $adjustment->balance_before,
                ];
            } catch (\Exception $e) {
                // Não faz ajuste, registra o erro
                if (str_contains($e->getMessage(), 'nenhum ajuste necessário')) {
                    $adjustments[] = [
                        'status' => 'no_difference',
                        'part_id' => $item['part_id'],
                    ];
                } else {
                    $adjustments[] = [
                        'status' => 'error',
                        'part_id' => $item['part_id'],
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return $adjustments;
    }
}
