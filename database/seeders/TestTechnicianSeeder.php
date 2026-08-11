<?php

namespace Database\Seeders;

use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Asset;
use Illuminate\Database\Seeder;

class TestTechnicianSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Criar tenant
        // 'features' precisa incluir 'tabela_maintenance_orders' explicitamente: sem plan_id,
        // Tenant::hasFeature() cai no fallback $this->plan?->hasFeature() = null = false, e
        // MaintenanceOrderPolicy::before() bloqueia com 403 mesmo o technician_id/tenant_id
        // batendo certinho (isso só aparece em request HTTP real, não em tinker sem Auth::user()).
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'teste-tecnico'],
            [
                'name' => 'Teste Técnico',
                'segment' => 'demo',
                'features' => ['tabela_maintenance_orders' => true],
            ]
        );
        echo "✓ Tenant: {$tenant->name}\n";

        // 2. Criar técnico
        $technician = User::firstOrCreate(
            ['email' => 'tecnico@teste.local'],
            [
                'name' => 'Técnico Teste',
                'password' => bcrypt('senha123'),
                'tenant_id' => $tenant->id,
                'is_approved' => true,
                'email_verified_at' => now(),
            ]
        );
        $technician->update(['email_verified_at' => now(), 'is_approved' => true]);
        echo "✓ Técnico: {$technician->name}\n";

        // 3. Criar admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@teste.local'],
            [
                'name' => 'Admin Teste',
                'password' => bcrypt('senha123'),
                'tenant_id' => $tenant->id,
                'is_approved' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->update(['email_verified_at' => now(), 'is_approved' => true]);
        $admin->assignRole('admin');
        echo "✓ Admin: {$admin->name}\n";

        // 6. Criar ativo
        $asset = Asset::firstOrCreate(
            ['patrimonio' => 'TESTE-001', 'tenant_id' => $tenant->id],
            [
                'name' => 'Bomba Centrífuga de Teste',
                'asset_category' => 'Equipamentos',
                'criticality_level' => 'Crítica',
                'status' => 'disponivel',
                'last_horimetro' => 100.50,
            ]
        );
        echo "✓ Ativo: {$asset->name} (PAT. {$asset->patrimonio})\n";


        // 10. Criar O.S. Preventiva
        $order = MaintenanceOrder::firstOrCreate(
            ['os_number' => 'OS-TEST-001', 'tenant_id' => $tenant->id],
            [
                'asset_id' => $asset->id,
                'technician_id' => $technician->id,
                'maintenance_type' => 'Preventiva',
                'status' => 'Aberto',
                'description' => 'Manutenção preventiva de teste para validar o fluxo do wizard de campo',
                'horimetro_anterior' => 100,
                'sla_target_minutes' => 480, // 8 horas
            ]
        );
        echo "✓ O.S.: {$order->os_number}\n";

        // 11. Adicionar itens de checklist
        $items = [
            'Verificar vazamentos de óleo' => 1,
            'Inspecionar mancais' => 2,
            'Testar rotação do eixo' => 3,
            'Verificar alinhamento' => 4,
            'Limpar filtros' => 5,
        ];

        foreach ($items as $itemName => $orderNum) {
            MaintenanceOrderChecklist::firstOrCreate(
                [
                    'maintenance_order_id' => $order->id,
                    'item_name' => $itemName,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'section' => 'Inspeção Geral',
                ]
            );
        }
        echo "✓ 5 itens de checklist adicionados\n";

        echo "\n=== DADOS DE TESTE ===\n";
        echo "Tenant: {$tenant->name}\n";
        echo "Técnico: {$technician->name} ({$technician->email})\n";
        echo "Senha: senha123\n";
        echo "Ativo: {$asset->name} (PAT. {$asset->patrimonio})\n";
        echo "O.S.: {$order->os_number}\n";
        echo "Tipo: Preventiva\n";
        echo "SLA: 8 horas\n";
        echo "\n✅ Pronto para testar o fluxo completo!\n";
    }
}
