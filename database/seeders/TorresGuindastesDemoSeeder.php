<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\ChecklistGroup;
use App\Models\Client;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SolicitacaoLocacao;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/**
 * Cenario de demonstracao comercial (guindaste em manutencao + proposta de
 * locacao + checklist de despacho com aprovacao do patio) -- usado nas
 * primeiras visitas de venda. Idempotente: pode rodar de novo sem duplicar
 * (usa firstOrCreate pelas chaves naturais). Senha padrao de todos os
 * usuarios de demo: "Demo@Oravel1".
 */
class TorresGuindastesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::firstOrCreate(
            ['name' => 'Plano Demo Comercial'],
            [
                'price' => 100, 'base_price' => 100, 'level' => 1,
                'billing_cycle' => 'monthly', 'is_active' => true,
                'features' => [
                    'tabela_assets', 'tabela_asset_categories', 'tabela_clients', 'tabela_contracts',
                    'tabela_departments', 'tabela_maintenance_orders', 'tabela_maintenance_plans',
                    'tabela_preventive_maintenance_executions', 'tabela_roles', 'tabela_users',
                    'tabela_checklist_groups', 'tabela_checklist_templates', 'tabela_equipment_movements',
                    'tabela_equipment_damages', 'tabela_equipment_replacements', 'tabela_solicitacao_locacao',
                    'tabela_materials', 'tabela_material_categories', 'tabela_suppliers',
                    'tabela_parts_requests', 'tabela_user_activity_logs', 'tabela_abc_matrix',
                ],
            ]
        );

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'torres-guindastes'],
            ['name' => 'Torres & Guindastes', 'plan_id' => $plan->id, 'status' => 'active']
        );

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $tecnicoRole = Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $comercialRole = Role::firstOrCreate(['name' => 'Comercial', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        foreach (['ler_solicitacao_locacao', 'criar_solicitacao_locacao', 'editar_solicitacao_locacao', 'excluir_solicitacao_locacao'] as $permName) {
            $comercialRole->givePermissionTo(Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']));
        }

        $password = Hash::make('Demo@Oravel1');

        $admin = User::firstOrCreate(
            ['email' => 'admin@torresguindastes.com.br'],
            ['name' => 'Admin Torres & Guindastes', 'password' => $password, 'tenant_id' => $tenant->id]
        );
        $admin->forceFill(['email_verified_at' => now()])->save();
        if (! $admin->hasRole('admin')) {
            $admin->assignRole($adminRole);
        }

        $tecnico = User::firstOrCreate(
            ['email' => 'tecnico@torresguindastes.com.br'],
            ['name' => 'Técnico Torres & Guindastes', 'password' => $password, 'tenant_id' => $tenant->id]
        );
        $tecnico->forceFill(['email_verified_at' => now()])->save();
        if (! $tecnico->hasRole('tecnico')) {
            $tecnico->assignRole($tecnicoRole);
        }

        $comercial = User::firstOrCreate(
            ['email' => 'comercial@torresguindastes.com.br'],
            ['name' => 'Comercial Torres & Guindastes', 'password' => $password, 'tenant_id' => $tenant->id]
        );
        $comercial->forceFill(['email_verified_at' => now()])->save();
        if (! $comercial->hasRole('Comercial')) {
            $comercial->assignRole($comercialRole);
        }

        $client = Client::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Construtora Anel Viário Campinas']
        );

        $category = AssetCategory::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Guindaste Rodoviário Telescópico']
        );

        $checklistGroup = ChecklistGroup::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Guindastes']
        );

        foreach ([
            'LMI', 'Freios de Giro e Elevação', 'Cabo e Moitão',
            'Contra-peso', 'Nivelamento do Guindaste', 'Mangueiras Hidráulicas',
        ] as $itemName) {
            MaintenanceOrderChecklist::firstOrCreate([
                'tenant_id' => $tenant->id,
                'checklist_group_id' => $checklistGroup->id,
                'item_name' => $itemName,
                'is_template' => true,
            ]);
        }

        $asset = Asset::firstOrCreate(
            ['tenant_id' => $tenant->id, 'patrimonio' => 'PAT-LBH-100'],
            [
                'name' => 'Guindaste Liebherr 100T',
                'serial_number' => 'LBH-100',
                'status' => Asset::STATUS_MANUTENCAO,
                'asset_category' => 'Guindaste Rodoviário Telescópico',
                'checklist_group_id' => $checklistGroup->id,
                'description' => 'Troca de cabos de aço e preventiva',
            ]
        );

        MaintenanceOrder::firstOrCreate(
            ['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Troca de cabos de aço e preventiva'],
            [
                'technician_id' => $tecnico->id,
                'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
                'status' => 'Em Andamento',
                'internal_status' => 'teste_qualidade',
            ]
        );

        SolicitacaoLocacao::firstOrCreate(
            ['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'customer_id' => $client->id],
            [
                'user_id' => $admin->id,
                'category_id' => $category->id,
                'purpose' => 'Içamento de estruturas metálicas na obra do anel viário (escopo longo)',
                'data_saida_prevista' => now()->addDays(15),
                'status_comercial' => 'proposta_em_andamento',
            ]
        );

        $this->command?->info('Torres & Guindastes: tenant, 3 usuários (senha Demo@Oravel1), ativo, OS, checklist e proposta de locação prontos.');
    }
}
