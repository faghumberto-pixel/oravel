<?php

namespace App\Services;

use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderPart;
use App\Models\Part;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * Almoxarifado Volante: transferência de peças do depósito central pro
 * veículo/técnico de campo, e apropriação direta na Ordem de Serviço.
 *
 * Cada movimentação gera 2 linhas em stock_movements (transfer_out no
 * armazém de origem, transfer_in no de destino) -- mesmo padrão de kardex
 * de linha única já usado pelo resto do módulo de Almoxarifado, em vez de
 * colunas source/destination na mesma linha.
 */
class StockTransferService
{
    /**
     * @param  array<int, array{part_id: int, quantity: float}>  $items
     * @return array<int, StockMovement> as 2 linhas (transfer_out + transfer_in) de cada item
     *
     * @throws \RuntimeException se algum item não tiver saldo suficiente no armazém de origem
     */
    public function transferToMobile(int $sourceId, int $mobileWarehouseId, array $items, string $userId): array
    {
        $source = Warehouse::findOrFail($sourceId);
        $destination = Warehouse::findOrFail($mobileWarehouseId);

        if (! $destination->isMobile()) {
            throw new \RuntimeException('O armazém de destino precisa ser do tipo Volante.');
        }

        return DB::transaction(function () use ($source, $destination, $items, $userId) {
            $movements = [];

            foreach ($items as $item) {
                $part = Part::findOrFail($item['part_id']);
                $quantity = (float) $item['quantity'];

                if ($quantity <= 0) {
                    throw new \RuntimeException("Quantidade inválida para a peça \"{$part->name}\".");
                }

                $sourceStock = WarehouseStock::firstOrCreate(
                    ['warehouse_id' => $source->id, 'part_id' => $part->id],
                    ['current_quantity' => 0, 'reserved_quantity' => 0]
                );

                if ((float) $sourceStock->current_quantity < $quantity) {
                    throw new \RuntimeException(
                        "Saldo insuficiente de \"{$part->name}\" no armazém de origem ".
                        "({$sourceStock->current_quantity} disponível, {$quantity} solicitado)."
                    );
                }

                $destinationStock = WarehouseStock::firstOrCreate(
                    ['warehouse_id' => $destination->id, 'part_id' => $part->id],
                    ['current_quantity' => 0, 'reserved_quantity' => 0]
                );

                $sourceBalanceBefore = (float) $sourceStock->current_quantity;
                $sourceBalanceAfter = $sourceBalanceBefore - $quantity;
                $destinationBalanceBefore = (float) $destinationStock->current_quantity;
                $destinationBalanceAfter = $destinationBalanceBefore + $quantity;
                $unitCost = (float) $part->cost_price;

                $sourceStock->update(['current_quantity' => $sourceBalanceAfter]);
                $destinationStock->update(['current_quantity' => $destinationBalanceAfter]);

                $movements[] = StockMovement::create([
                    'tenant_id' => $source->tenant_id,
                    'part_id' => $part->id,
                    'warehouse_id' => $source->id,
                    'movement_type' => 'transfer_out',
                    'quantity' => $quantity,
                    'balance_before' => $sourceBalanceBefore,
                    'balance_after' => $sourceBalanceAfter,
                    'unit_cost' => $unitCost,
                    'total_cost' => round($unitCost * $quantity, 2),
                    'reference_document' => "Transferência para {$destination->name}",
                    'created_by' => $userId,
                ]);

                $movements[] = StockMovement::create([
                    'tenant_id' => $destination->tenant_id,
                    'part_id' => $part->id,
                    'warehouse_id' => $destination->id,
                    'movement_type' => 'transfer_in',
                    'quantity' => $quantity,
                    'balance_before' => $destinationBalanceBefore,
                    'balance_after' => $destinationBalanceAfter,
                    'unit_cost' => $unitCost,
                    'total_cost' => round($unitCost * $quantity, 2),
                    'reference_document' => "Transferência de {$source->name}",
                    'created_by' => $userId,
                ]);
            }

            return $movements;
        });
    }

    /**
     * Detecta automaticamente o Almoxarifado Volante do técnico
     * autenticado (Warehouse type=mobile, user_id=$technicianUserId) e
     * debita a peça aplicada na OS de lá -- nunca do estoque central
     * diretamente, forçando o fluxo real (peça precisa ter sido
     * transferida pro veículo antes de ser aplicada).
     *
     * $allowNegative: quando true e o saldo for insuficiente, permite a
     * baixa mesmo assim (fica negativo) e alerta o admin -- pedido do
     * usuário como alternativa ao bloqueio total, pro caso de a
     * transferência não ter sido registrada a tempo mas a peça já ter
     * sido de fato usada em campo.
     *
     * @throws \RuntimeException se o técnico não tiver Almoxarifado Volante vinculado, ou
     *                           se o saldo for insuficiente e $allowNegative for false
     */
    public function consumePartInWorkOrder(
        string $workOrderId,
        int $partId,
        float $qty,
        string $technicianUserId,
        bool $allowNegative = false
    ): MaintenanceOrderPart {
        $warehouse = Warehouse::mobileForUser($technicianUserId)->first();

        if (! $warehouse) {
            throw new \RuntimeException(
                'Você não tem um Almoxarifado Volante vinculado. Peça ao administrador para cadastrar seu veículo.'
            );
        }

        $part = Part::findOrFail($partId);
        $workOrder = MaintenanceOrder::findOrFail($workOrderId);

        if ($qty <= 0) {
            throw new \RuntimeException('Quantidade inválida.');
        }

        return DB::transaction(function () use ($warehouse, $part, $workOrder, $qty, $technicianUserId, $allowNegative) {
            $stock = WarehouseStock::firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'part_id' => $part->id],
                ['current_quantity' => 0, 'reserved_quantity' => 0]
            );

            $balanceBefore = (float) $stock->current_quantity;
            $isNegative = $balanceBefore < $qty;

            if ($isNegative && ! $allowNegative) {
                throw new \RuntimeException(
                    "Saldo insuficiente de \"{$part->name}\" no seu veículo ".
                    "({$balanceBefore} disponível, {$qty} necessário). Solicite uma transferência antes de aplicar."
                );
            }

            $balanceAfter = $balanceBefore - $qty;
            $unitCost = (float) $part->cost_price;

            $stock->update(['current_quantity' => $balanceAfter]);

            $movement = StockMovement::create([
                'tenant_id' => $warehouse->tenant_id,
                'part_id' => $part->id,
                'warehouse_id' => $warehouse->id,
                'work_order_id' => $workOrder->id,
                'movement_type' => 'exit_work_order',
                'quantity' => $qty,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'unit_cost' => $unitCost,
                'total_cost' => round($unitCost * $qty, 2),
                'reference_document' => $workOrder->os_number,
                'created_by' => $technicianUserId,
            ]);

            $orderPart = MaintenanceOrderPart::create([
                'tenant_id' => $warehouse->tenant_id,
                'maintenance_order_id' => $workOrder->id,
                'part_id' => $part->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => $qty,
                'unit_price' => $unitCost,
                'stock_movement_id' => $movement->id,
            ]);

            $this->recalculateOrderCosts($workOrder);

            if ($isNegative) {
                $this->alertNegativeStock($warehouse, $part, $balanceAfter);
            }

            return $orderPart;
        });
    }

    /**
     * material_cost passa a somar Material (catálogo antigo) + Part
     * (Almoxarifado Volante) -- um único campo de "custo de material" na
     * OS, sem quebrar dashboards/relatórios que já leem material_cost.
     */
    private function recalculateOrderCosts(MaintenanceOrder $order): void
    {
        $materialCost = $order->materials()
            ->get()
            ->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price);

        $partsCost = $order->catalogParts()
            ->get()
            ->sum(fn (MaintenanceOrderPart $item) => (float) $item->quantity * (float) $item->unit_price);

        $totalMaterialCost = $materialCost + $partsCost;

        $order->update([
            'material_cost' => $totalMaterialCost,
            'total_order_cost' => $totalMaterialCost + (float) $order->labor_cost + (float) $order->logistics_cost,
        ]);
    }

    /**
     * Mesmo cuidado documentado em EquipmentReplacementObserver::notifyRole()
     * -- não usar User::role('admin') direto, o Spatie resolve por nome
     * globalmente (ignora tenant_id).
     */
    private function alertNegativeStock(Warehouse $warehouse, Part $part, float $balanceAfter): void
    {
        $role = Role::where('name', 'admin')
            ->where('guard_name', 'web')
            ->where('tenant_id', $warehouse->tenant_id)
            ->first();

        if (! $role) {
            return;
        }

        $recipients = User::role($role)->where('tenant_id', $warehouse->tenant_id)->get();

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title('Saldo negativo em Almoxarifado Volante')
                ->body("\"{$part->name}\" ficou com saldo {$balanceAfter} no veículo \"{$warehouse->name}\" — regularize a transferência.")
                ->warning()
                ->sendToDatabase($recipient);
        }
    }
}
