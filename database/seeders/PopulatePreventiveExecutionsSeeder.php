<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PopulatePreventiveExecutionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = \App\Models\Tenant::where('name', 'Eletraq')->first();
        if (!$tenant) return;

        $plans = \App\Models\MaintenancePlan::where('tenant_id', $tenant->id)->get();
        $assets = \App\Models\Asset::where('tenant_id', $tenant->id)->get();
        $technicians = \App\Models\User::where('tenant_id', $tenant->id)->get();

        $statuses = ['aguardando_diagnostico', 'em_manutencao', 'aguardando_peca', 'teste_qualidade', 'pronto_giro', 'concluido'];

        foreach ($plans->take(5) as $plan) {
            foreach ($assets->take(3) as $asset) {
                if ($technicians->isEmpty()) continue;

                // Cria MaintenanceOrder primeiro
                $order = \App\Models\MaintenanceOrder::create([
                    'tenant_id' => $tenant->id,
                    'asset_id' => $asset->id,
                    'maintenance_type' => 'Preventiva',
                    'status' => 'Aberto',
                    'internal_status' => $statuses[array_rand($statuses)],
                    'scheduled_at' => now()->subDays(rand(0, 7)),
                ]);

                // Cria execução preventiva
                \App\Models\PreventiveMaintenanceExecution::create([
                    'tenant_id' => $tenant->id,
                    'maintenance_order_id' => $order->id,
                    'maintenance_plan_id' => $plan->id,
                    'asset_id' => $asset->id,
                    'technician_id' => $technicians->random()->id,
                    'horimetro_at_execution' => rand(100, 10000),
                    'next_due_horimetro' => rand(10000, 50000),
                    'created_at' => now()->subDays(rand(0, 7)),
                ]);
            }
        }

        $this->command->info('✅ ' . \App\Models\PreventiveMaintenanceExecution::where('tenant_id', $tenant->id)->count() . ' execuções preventivas criadas para Eletraq');
    }
}
