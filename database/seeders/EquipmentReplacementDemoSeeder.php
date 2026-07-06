<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Contract;
use App\Models\EquipmentDamage;
use App\Models\EquipmentMovement;
use App\Models\EquipmentReplacement;
use App\Models\MaintenanceOrder;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Popula o processo de Troca de Equipamento com dados reais dos tenants ja
 * existentes (assets/OS/avarias reais, nao fabricados do zero), cobrindo os
 * 6 estagios do fluxo. Passa pelos metodos reais do model
 * (identifyReplacement/startLogisticsMovements + conclusao dos movements)
 * em vez de so setar o status na mao, pra cada linha ficar consistente com
 * os efeitos colaterais reais (notificacoes, troca de contrato/status do
 * ativo). Idempotente por tenant: pula se ja existir alguma troca.
 *
 * Uso: php artisan db:seed --class=EquipmentReplacementDemoSeeder
 */
class EquipmentReplacementDemoSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::all()->each(function (Tenant $tenant) {
            if (EquipmentReplacement::where('tenant_id', $tenant->id)->exists()) {
                $this->command?->info("Tenant '{$tenant->name}' já tem trocas de equipamento -- pulando.");

                return;
            }

            $orders = MaintenanceOrder::where('tenant_id', $tenant->id)
                ->whereNotNull('technician_id')
                ->whereNotNull('asset_id')
                ->get();

            if ($orders->isEmpty()) {
                return;
            }

            $availableAssets = Asset::where('tenant_id', $tenant->id)
                ->where('status', Asset::STATUS_DISPONIVEL)
                ->inRandomOrder()
                ->get();

            // Nao usar User::role(string) direto: Spatie resolve o papel por
            // nome globalmente (ignora tenant_id), ver
            // EquipmentReplacementObserver::notifyRole() pro mesmo bug.
            $commercialRole = Role::where('name', EquipmentDamage::ROLE_COMERCIAL)
                ->where('guard_name', 'web')
                ->where('tenant_id', $tenant->id)
                ->first();
            $commercialUser = $commercialRole
                ? User::role($commercialRole)->where('tenant_id', $tenant->id)->first()
                : null;

            // Reserva um ativo so pro cenario 5 (o unico que efetivamente muda
            // o status do substituto pra "locado"); os cenarios 2-4 reaproveitam
            // o mesmo ativo "compartilhado" ja que nenhum deles altera seu status.
            $swapAsset = $availableAssets->shift();
            $sharedAsset = $availableAssets->first();

            // 1. Solicitado -- so o pedido do tecnico, nada mais aconteceu ainda.
            $this->createRequest($tenant, $orders->random());

            // 2. Substituto identificado -- comercial ja definiu o ativo, movements nao criados.
            if ($commercialUser && $sharedAsset) {
                $replacement = $this->createRequest($tenant, $orders->random());
                $replacement->identifyReplacement($sharedAsset, $commercialUser);
            }

            // 3. Desmobilizacao em andamento -- movements criados e vinculados, nenhum concluido ainda.
            if ($commercialUser && $sharedAsset) {
                $replacement = $this->createRequest($tenant, $orders->random());
                $replacement->identifyReplacement($sharedAsset, $commercialUser);
                $replacement->startLogisticsMovements();
            }

            // 4. Mobilizacao em andamento -- desmobilizacao ja concluida.
            if ($commercialUser && $sharedAsset) {
                $replacement = $this->createRequest($tenant, $orders->random());
                $replacement->identifyReplacement($sharedAsset, $commercialUser);
                $replacement->startLogisticsMovements();
                $replacement->desmobilizationMovement->update([
                    'status' => EquipmentMovement::STATUS_CONCLUIDO,
                    'completed_at' => now()->subDays(2),
                ]);
            }

            // 5. Concluido -- ambos movements concluidos + assinatura, dispara o swap real
            // de contrato/status do ativo (contrato real se o tenant tiver um pra esse ativo).
            if ($commercialUser && $replacementAsset = $swapAsset) {
                $orderForSwap = $this->findOrderWithActiveContract($tenant, $orders) ?? $orders->random();

                $replacement = $this->createRequest($tenant, $orderForSwap);
                $replacement->identifyReplacement($replacementAsset, $commercialUser);
                $replacement->startLogisticsMovements();
                $replacement->desmobilizationMovement->update([
                    'status' => EquipmentMovement::STATUS_CONCLUIDO,
                    'completed_at' => now()->subDays(5),
                ]);
                $replacement->mobilizationMovement->update([
                    'status' => EquipmentMovement::STATUS_CONCLUIDO,
                    'completed_at' => now()->subDay(),
                    'client_signature' => $this->fakeSignature(),
                ]);
            }

            // 6. Cancelado.
            $cancelledOrder = $orders->random();
            EquipmentReplacement::factory()->cancelado()->create([
                'tenant_id' => $tenant->id,
                'maintenance_order_id' => $cancelledOrder->id,
                'original_asset_id' => $cancelledOrder->asset_id,
                'requested_by_user_id' => $cancelledOrder->technician_id,
            ]);

            // 7. Vinculado a uma avaria real (se existir alguma no tenant).
            $damage = EquipmentDamage::where('tenant_id', $tenant->id)->first();
            if ($damage) {
                $damage->update(['requires_replacement' => true]);

                EquipmentReplacement::factory()->create([
                    'tenant_id' => $tenant->id,
                    'maintenance_order_id' => $damage->maintenance_order_id,
                    'equipment_damage_id' => $damage->id,
                    'original_asset_id' => $damage->asset_id,
                    'requested_by_user_id' => $damage->reported_by_user_id,
                ]);
            }

            $this->command?->info("Tenant '{$tenant->name}': trocas de equipamento semeadas.");
        });
    }

    private function createRequest(Tenant $tenant, MaintenanceOrder $order): EquipmentReplacement
    {
        return EquipmentReplacement::factory()->create([
            'tenant_id' => $tenant->id,
            'maintenance_order_id' => $order->id,
            'original_asset_id' => $order->asset_id,
            'requested_by_user_id' => $order->technician_id,
        ]);
    }

    private function findOrderWithActiveContract(Tenant $tenant, $orders): ?MaintenanceOrder
    {
        $activeContractAssetIds = Contract::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->pluck('asset_id');

        return $orders->first(fn (MaintenanceOrder $order) => $activeContractAssetIds->contains($order->asset_id));
    }

    private function fakeSignature(): string
    {
        return 'data:image/png;base64,'.base64_encode('demo-signature');
    }
}
