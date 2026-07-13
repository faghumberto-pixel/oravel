<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\ChecklistGroup;
use App\Models\Client;
use App\Models\CriticalityLevel;
use App\Models\FleetVehicle;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use App\Models\MaintenanceOrderMaterial;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Cenario de demonstracao comercial de patio tecnico (locadora/oficina de
 * geradores) -- gerador critico com contrato no dia seguinte, custo de
 * mao de obra + peca + deslocamento previstos na propria O.S., e o Kanban
 * destacando a prioridade. Idempotente: se o tenant 'geradores-rmc' ja
 * existe, nao roda de novo.
 *
 * Uso: php artisan db:seed --class=DemoGeradoresRmcSeeder
 */
class DemoGeradoresRmcSeeder extends Seeder
{
    private const SLUG = 'geradores-rmc';

    private const OFICINA_ENDERECO = 'Av. Eng. Antônio Francisco de Paula Souza - Jardim São Vicente, Campinas - SP';

    private const CLIENTE_ENDERECO = 'Av. Itatiaia, 490-612 - Jardim Itatiaia, Campinas - SP';

    public function run(): void
    {
        if (Tenant::where('slug', self::SLUG)->exists()) {
            $this->command?->info("Tenant '".self::SLUG."' já existe -- pulando (idempotente).");

            return;
        }

        $this->command?->info('Semeando tenant Geradores RMC...');

        $plan = $this->ensurePlan();

        $tenant = Tenant::create([
            'name' => 'Geradores RMC',
            'slug' => self::SLUG,
            'status' => 'active',
            'address' => self::OFICINA_ENDERECO,
            'plan_id' => $plan->id,
            'onboarding_completed' => true,
        ]);

        $admin = TenantProvisioner::provision($tenant, [
            'name' => 'Admin Geradores RMC',
            'email' => 'admin@geradoresrmc.com.br',
            'password' => 'Demo@Oravel1',
        ]);

        $tecnico = $this->seedMecanico($tenant);
        $checklistGroups = $this->seedChecklistGroupsAndTemplates($tenant);
        $material = $this->seedMaterial($tenant, $checklistGroups['Geradores']);
        $this->seedFleetVehicle($tenant);
        $criticalidades = $this->seedCriticalityLevels($tenant);
        $cliente = $this->seedCliente($tenant);

        $assets = $this->seedAssets($tenant, $checklistGroups);
        $this->seedMaintenanceOrders($tenant, $assets, $tecnico, $material, $criticalidades, $cliente);

        $this->command?->info('Geradores RMC: cenário de demo pronto (admin@geradoresrmc.com.br / Demo@Oravel1).');
    }

    /**
     * Mesmo plano usado pelo Torres & Guindastes -- "Plano Demo Comercial"
     * e' feito pra ser reaproveitado por qualquer tenant de demonstracao.
     * So garante que a feature de frota esteja incluida (aditivo, nao
     * remove nada do que o Torres ja usa).
     */
    private function ensurePlan(): Plan
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
                    'tabela_fleet_vehicles',
                ],
            ]
        );

        $features = $plan->features ?? [];
        if (! in_array('tabela_fleet_vehicles', $features, true)) {
            $features[] = 'tabela_fleet_vehicles';
            $plan->update(['features' => $features]);
        }

        return $plan;
    }

    private function seedMecanico(Tenant $tenant): User
    {
        $role = Role::firstOrCreate(
            ['name' => 'tecnico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]
        );

        $user = User::firstOrCreate(
            ['email' => 'mecanico@geradoresrmc.com.br'],
            [
                'name' => 'Mecânico Técnico RMC',
                'password' => Hash::make('Demo@Oravel1'),
                'tenant_id' => $tenant->id,
                'role' => 'tecnico',
                'hourly_rate' => 40.00,
            ]
        );
        $user->forceFill(['email_verified_at' => now()])->save();
        if (! $user->hasRole('tecnico')) {
            $user->assignRole($role);
        }

        return $user;
    }

    /**
     * Itens de checklist-template por grupo, mesmo texto de
     * BasicChecklistTemplateSeeder -- nao dá pra chamar aquele seeder
     * direto (roda sobre Tenant::all(), afetaria todo mundo), entao
     * embutido aqui, escopado so a esse tenant.
     *
     * @return array<string, ChecklistGroup>
     */
    private function seedChecklistGroupsAndTemplates(Tenant $tenant): array
    {
        $defs = [
            'Geradores' => [
                'objetivo' => 'Evitar falhas de partida e superaquecimento.',
                'itens' => [
                    ['item_name' => 'Nível de Óleo do Motor', 'instructions' => 'Dentro da faixa'],
                    ['item_name' => 'Nível do Fluido de Arrefecimento', 'instructions' => 'Radiador'],
                    ['item_name' => 'Combustível', 'instructions' => 'Nível no tanque'],
                    ['item_name' => 'Bateria', 'instructions' => 'Tensão, oxidação nos bornes'],
                    ['item_name' => 'Filtro de Combustível Separador', 'instructions' => 'Estado/troca conforme horímetro'],
                    ['item_name' => 'Painel de Controle', 'instructions' => 'Sem alarmes/mensagens'],
                ],
            ],
        ];

        $groups = [];
        foreach ($defs as $name => $def) {
            $group = ChecklistGroup::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                ['description' => $def['objetivo']]
            );
            $groups[$name] = $group;

            foreach ($def['itens'] as $item) {
                MaintenanceOrderChecklist::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'checklist_group_id' => $group->id,
                        'item_name' => $item['item_name'],
                        'is_template' => true,
                    ],
                    [
                        'category' => $name,
                        'instructions' => $item['instructions'],
                        'section' => $name,
                        'checklist_type' => 'Preventiva',
                        'is_completed' => false,
                    ]
                );
            }
        }

        return $groups;
    }

    /**
     * Nao existe pivot "peca compatível com marca de motor" no sistema --
     * o vinculo real mais proximo e' Material::checklistGroups() (mesmo
     * mecanismo que o app ja usa pra sugerir peca por tipo de
     * equipamento), mais o proprio nome do material ja deixando
     * "Cummins/MWM" explicito.
     */
    private function seedMaterial(Tenant $tenant, ChecklistGroup $geradoresGroup): Material
    {
        $category = MaterialCategory::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Filtros']
        );

        $material = Material::firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => 'RMC-FILTRO-CUMMINS-MWM'],
            [
                'name' => 'Filtro de Combustível Separador Cummins/MWM',
                'material_category_id' => $category->id,
                'unit_cost' => 150.00,
                'price' => 195.00,
                'min_stock' => 4,
                'max_stock' => 30,
                'current_stock' => 12,
            ]
        );

        if (! $material->checklistGroups()->where('checklist_groups.id', $geradoresGroup->id)->exists()) {
            $material->checklistGroups()->attach($geradoresGroup->id);
        }

        return $material;
    }

    private function seedFleetVehicle(Tenant $tenant): FleetVehicle
    {
        return FleetVehicle::firstOrCreate(
            ['tenant_id' => $tenant->id, 'placa' => 'RMC1A23'],
            [
                'modelo' => 'Fiat Fiorino — Carro de Apoio/Oficina',
                'tipo' => 'outro',
                'status' => FleetVehicle::STATUS_DISPONIVEL,
                'km_atual' => 32000,
            ]
        );
    }

    /** @return array<string, CriticalityLevel> */
    private function seedCriticalityLevels(Tenant $tenant): array
    {
        $defs = [
            ['code' => 'baixa', 'name' => 'Baixa', 'color' => '#22c55e'],
            ['code' => 'media', 'name' => 'Média', 'color' => '#f59e0b'],
            ['code' => 'alta', 'name' => 'Alta', 'color' => '#ef4444'],
        ];

        $levels = [];
        foreach ($defs as $def) {
            $levels[$def['code']] = CriticalityLevel::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $def['code']],
                ['name' => $def['name'], 'color' => $def['color']]
            );
        }

        return $levels;
    }

    private function seedCliente(Tenant $tenant): Client
    {
        return Client::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Contrato Jardim Itatiaia'],
            [
                'address' => self::CLIENTE_ENDERECO,
                'city' => 'Campinas',
                'uf' => 'SP',
            ]
        );
    }

    /** @return array<string, Asset> */
    private function seedAssets(Tenant $tenant, array $checklistGroups): array
    {
        $category = AssetCategory::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Gerador']
        );

        $geradoresGroup = $checklistGroups['Geradores'];

        $assets = [];

        $assets['cummins'] = Asset::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tag' => 'GER-CUMMINS-150'],
            [
                'name' => 'Gerador Cummins 150 kVA',
                'patrimonio' => 'PAT-RMC-001',
                'serial_number' => 'CUM150-0001',
                'status' => Asset::STATUS_MANUTENCAO,
                'asset_category' => $category->name,
                'checklist_group_id' => $geradoresGroup->id,
                'capacity_value' => 150,
                'capacity_unit' => 'kVA',
                'description' => 'Em Teste de Carga',
            ]
        );

        $assets['mwm'] = Asset::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tag' => 'GER-MWM-250'],
            [
                'name' => 'Gerador MWM 250 kVA',
                'patrimonio' => 'PAT-RMC-002',
                'serial_number' => 'MWM250-0001',
                'status' => Asset::STATUS_AGUARDANDO_TRIAGEM,
                'asset_category' => $category->name,
                'checklist_group_id' => $geradoresGroup->id,
                'capacity_value' => 250,
                'capacity_unit' => 'kVA',
                'description' => 'Lavagem/Retorno — aguardando inspeção',
            ]
        );

        $assets['scania'] = Asset::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tag' => 'GER-SCANIA-500'],
            [
                'name' => 'Gerador Scania 500 kVA',
                'patrimonio' => 'PAT-RMC-003',
                'serial_number' => 'SCA500-0001',
                'status' => Asset::STATUS_MANUTENCAO,
                'asset_category' => $category->name,
                'checklist_group_id' => $geradoresGroup->id,
                'capacity_value' => 500,
                'capacity_unit' => 'kVA',
                'description' => 'Aguardando Preventiva',
            ]
        );

        return $assets;
    }

    private function seedMaintenanceOrders(
        Tenant $tenant,
        array $assets,
        User $tecnico,
        Material $material,
        array $criticalidades,
        Client $cliente,
    ): void {
        // Gerador Cummins -- "Em Teste de Carga": mapeia pra coluna real
        // do Kanban "Teste de Qualidade" (teste_qualidade).
        MaintenanceOrder::firstOrCreate(
            ['tenant_id' => $tenant->id, 'asset_id' => $assets['cummins']->id, 'description' => 'Teste de carga pós-manutenção'],
            [
                'technician_id' => $tecnico->id,
                'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
                'status' => 'Em Andamento',
                'internal_status' => 'teste_qualidade',
                'hours_spent' => 1.5,
            ]
        );

        // Gerador MWM -- "Lavagem/Retorno": mapeia pra coluna real
        // "Aguardando Diagnóstico" (recebimento/inspecao, primeira etapa).
        MaintenanceOrder::firstOrCreate(
            ['tenant_id' => $tenant->id, 'asset_id' => $assets['mwm']->id, 'description' => 'Recebimento e inspeção pós-locação'],
            [
                'technician_id' => $tecnico->id,
                'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
                'status' => 'Aberto',
                'internal_status' => 'aguardando_diagnostico',
            ]
        );

        // Gerador Scania -- O TRUNFO DA DEMO: criticidade alta, custos
        // previstos (mao de obra + peca + deslocamento), cliente com
        // contrato no dia seguinte.
        $scania = MaintenanceOrder::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'asset_id' => $assets['scania']->id,
                'description' => 'CRITICIDADE ALTA - CONTRATO AMANHÃ 08:00 HRS',
            ],
            [
                'technician_id' => $tecnico->id,
                'client_id' => $cliente->id,
                'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
                'status' => 'Aberto',
                'internal_status' => 'aguardando_diagnostico',
                'criticality_level_id' => $criticalidades['alta']->id,
                // Mao de obra: 2h previstas x R$40,00/h (hourly_rate do mecanico).
                'hours_spent' => 2.00,
                'labor_cost' => 80.00,
                // Peca: 1x Filtro de Combustivel Separador Cummins/MWM (unit_cost R$150,00).
                'material_cost' => 150.00,
                // Logistica/combustivel simulado: oficina (Jardim Sao Vicente) ->
                // cliente (Jardim Itatiaia), ida e volta ~13,5km x 2 = ~27km,
                // consumo/preco de combustivel do carro-oficina assumidos pra
                // chegar num valor redondo de apresentacao (~R$35,00).
                'logistics_cost' => 35.00,
                'total_order_cost' => 265.00,
            ]
        );

        if (! MaintenanceOrderMaterial::where('maintenance_order_id', $scania->id)->where('material_id', $material->id)->exists()) {
            MaintenanceOrderMaterial::create([
                'tenant_id' => $tenant->id,
                'maintenance_order_id' => $scania->id,
                'material_id' => $material->id,
                'name' => $material->name,
                'quantity' => 1,
                'unit_price' => 150.00,
            ]);
        }
    }
}
