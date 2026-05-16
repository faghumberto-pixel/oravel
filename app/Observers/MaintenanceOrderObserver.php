<?php

namespace App\Observers;

use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use App\Models\LogisticsQueue;
use App\Models\FleetStatus;
use Illuminate\Support\Str;

class MaintenanceOrderObserver
{
    /**
     * Gatilho de Entrada: Criado -> Define status inicial e gera checklist.
     */
    public function created(MaintenanceOrder $maintenanceOrder): void
    {
        // --- LÓGICA DO COMMAND CENTER (NOVA) ---
        // Define o status interno inicial para o Kanban
        $maintenanceOrder->updateQuietly([
            'internal_status' => 'aguardando_diagnostico'
        ]);

        // --- SUA LÓGICA DE CHECKLIST (PRESERVADA) ---
        // Garante que carregamos a relação 'asset' antes de acessar
        $asset = $maintenanceOrder->asset()->first();
        
        // Verifica se o ativo existe e se possui itens de checklist (manual_items)
        if ($asset && !empty($asset->manual_items) && is_array($asset->manual_items)) {
            foreach ($asset->manual_items as $item) {
                MaintenanceOrderChecklist::create([
                    'id' => (string) Str::uuid(), 
                    'maintenance_order_id' => $maintenanceOrder->id,
                    'category' => $item['category'] ?? 'geral',
                    'item_name' => $item['item_name'] ?? 'Item sem nome',
                    'instructions' => $item['instructions'] ?? null,
                    'is_completed' => false,
                ]);
            }
        }
    }

    /**
     * Gatilho de Automação: Atualizado -> Movimentação do Kanban e Integrações.
     */
    public function updated(MaintenanceOrder $maintenanceOrder): void
    {
        // 1. Início do Trabalho: Ao dar "Play" no cronômetro
        if ($maintenanceOrder->isDirty('last_timer_start') && $maintenanceOrder->last_timer_start !== null) {
            $maintenanceOrder->updateQuietly(['internal_status' => 'em_manutencao']);
        }

        // 2. Finalização Técnica: Ao mudar status para 'Concluída' via formulário/PWA
        if ($maintenanceOrder->isDirty('status') && $maintenanceOrder->status === 'Concluída') {
            // Move para revisão de qualidade antes de liberar para o comercial
            $maintenanceOrder->updateQuietly(['internal_status' => 'teste_qualidade']);
        }

        // 3. Liberação Comercial: Se o status interno mudar para 'disponivel_comercial'
        // Geralmente disparado por uma Action de aprovação do Supervisor no Kanban
        if ($maintenanceOrder->isDirty('internal_status') && $maintenanceOrder->internal_status === 'disponivel_comercial') {
            
            // Automatiza Logística de Retirada/Entrega
            LogisticsQueue::create([
                'maintenance_order_id' => $maintenanceOrder->id,
                'asset_id' => $maintenanceOrder->asset_id,
                'type' => 'entrega',
                'status' => 'aguardando',
                'destination' => 'Patio Principal'
            ]);

            // Atualiza status da frota para o time de Vendas
            FleetStatus::updateOrCreate(
                ['asset_id' => $maintenanceOrder->asset_id],
                [
                    'is_available' => true,
                    'last_maintenance_id' => $maintenanceOrder->id
                ]
            );
        }
    }
}