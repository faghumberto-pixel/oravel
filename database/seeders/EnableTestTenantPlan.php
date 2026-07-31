<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class EnableTestTenantPlan extends Seeder
{
    public function run(): void
    {
        // Encontrar ou criar um plano com todas as features
        $plan = Plan::where('is_active', true)->first();

        if (!$plan) {
            $plan = Plan::create([
                'name' => 'Teste Completo',
                'price' => 0,
                'base_price' => 0,
                'level' => 1,
                'billing_cycle' => 'monthly',
                'is_active' => true,
                'features' => [
                    'tabela_assets',
                    'tabela_maintenance_orders',
                    'tabela_materials',
                    'tabela_suppliers',
                    'tabela_clients',
                    'tabela_crm_leads',
                    'tabela_roles',
                    'modulo_chat',
                ],
            ]);
            echo "✓ Plano criado: " . $plan->name . "\n";
        } else {
            echo "✓ Plano encontrado: " . $plan->name . "\n";
        }

        // Atribuir ao tenant Teste Técnico
        $tenant = Tenant::where('slug', 'teste-tecnico')->first();
        if ($tenant) {
            $tenant->update(['plan_id' => $plan->id]);
            echo "✓ Plano atribuído ao tenant Teste Técnico\n";
        } else {
            echo "✗ Tenant Teste Técnico não encontrado\n";
        }
    }
}
