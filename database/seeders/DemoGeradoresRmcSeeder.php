<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\ChecklistGroup;
use App\Models\Client;
use App\Models\Contract;
use App\Models\CriticalityLevel;
use App\Models\Department;
use App\Models\EquipmentMovement;
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
        $tenant = Tenant::where('slug', self::SLUG)->first();

        // Chamado sempre (tenant novo ou ja existente) -- ensurePlan() e'
        // aditivo (so acrescenta feature que falte num plano ja criado),
        // entao tambem serve pra retrofit de tenants ja seedados antes de
        // uma feature nova (ex: modulo_chat) ter sido adicionada aqui.
        $plan = $this->ensurePlan();

        if (! $tenant) {
            $this->command?->info('Semeando tenant Geradores RMC...');

            $tenant = Tenant::create([
                'name' => 'Geradores RMC',
                'slug' => self::SLUG,
                'status' => 'active',
                'address' => self::OFICINA_ENDERECO,
                'plan_id' => $plan->id,
                'onboarding_completed' => true,
            ]);

            TenantProvisioner::provision($tenant, [
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

            // Contrato precisa existir ANTES da O.S. do Scania pra
            // MaintenanceOrder::booted()::creating() derivar
            // service_type='Externo' sozinho.
            $this->seedContratoScania($tenant, $assets['scania'], $cliente);
            $this->seedMaintenanceOrders($tenant, $assets, $tecnico, $material, $criticalidades, $cliente);
        } else {
            $this->command?->info("Tenant '".self::SLUG."' já existe -- aplicando só os ajustes novos (contrato/agenda/atribuição/setor).");

            $tecnico = User::where('tenant_id', $tenant->id)->where('email', 'mecanico@geradoresrmc.com.br')->firstOrFail();
            $scaniaAsset = Asset::where('tenant_id', $tenant->id)->where('tag', 'GER-SCANIA-500')->firstOrFail();
            $cliente = $this->seedCliente($tenant);

            $this->seedContratoScania($tenant, $scaniaAsset, $cliente);
        }

        $scaniaAsset = Asset::where('tenant_id', $tenant->id)->where('tag', 'GER-SCANIA-500')->first();
        $scaniaOs = MaintenanceOrder::where('tenant_id', $tenant->id)
            ->where('asset_id', $scaniaAsset->id)
            ->whereNotNull('criticality_level_id')
            ->first();

        if ($scaniaOs) {
            $this->seedMobilizacaoScania($tenant, $scaniaAsset, $scaniaOs);
            $this->ensureAtribuicaoHistorico($scaniaOs, $tecnico);
        }

        $this->seedSetorTesteSupervisao($tenant);

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
                    'tabela_fleet_vehicles', 'modulo_chat',
                ],
            ]
        );

        $features = $plan->features ?? [];
        $missing = array_diff(['tabela_fleet_vehicles', 'modulo_chat'], $features);
        if (! empty($missing)) {
            $plan->update(['features' => [...$features, ...array_values($missing)]]);
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

    /**
     * Vinculo real (nao so texto na descricao) entre a O.S. do Scania e um
     * contrato de locacao -- MaintenanceOrder::booted()::creating() ja
     * deriva service_type='Externo' sozinho quando existe Contract com
     * status='Ativo' pro mesmo asset_id.
     */
    private function seedContratoScania(Tenant $tenant, Asset $scania, Client $cliente): Contract
    {
        $contract = Contract::firstOrCreate(
            ['tenant_id' => $tenant->id, 'asset_id' => $scania->id, 'client_id' => $cliente->id],
            [
                'contract_number' => 'CT-RMC-0001',
                'status' => 'Ativo',
                'start_date' => now()->addDay(),
                'price' => 4200.00,
                'payment_method' => 'Boleto',
                'usage_purpose' => 'Locação para obra — geração de energia',
                'is_active' => true,
                'observations' => 'Retirada agendada para amanhã às 08:00.',
            ]
        );

        // Se a O.S. ja existia (retrofit de tenant ja seedado) sem contrato
        // ativo no momento da criacao, service_type nao se auto-corrige
        // sozinho (so e' derivado em creating()) -- forca aqui.
        MaintenanceOrder::where('tenant_id', $tenant->id)
            ->where('asset_id', $scania->id)
            ->where('service_type', '!=', 'Externo')
            ->update(['service_type' => 'Externo']);

        return $contract;
    }

    /**
     * EquipmentMovement pre-agendado (scheduled_at) -- e' o que faz o Scania
     * aparecer na Programacao/Centro de Comando como "programado pra sair"
     * amanha. EquipmentMovementMobile::mount() ja busca por
     * maintenance_order_id+type+status!=concluido antes de criar um novo,
     * entao o fluxo mobile do operador no patio reaproveita este mesmo
     * registro quando ele comecar de verdade -- sem duplicar.
     */
    private function seedMobilizacaoScania(Tenant $tenant, Asset $scania, MaintenanceOrder $scaniaOs): void
    {
        $amanha8h = now()->addDay()->setTime(8, 0);

        EquipmentMovement::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'asset_id' => $scania->id,
                'maintenance_order_id' => $scaniaOs->id,
                'type' => EquipmentMovement::TYPE_MOBILIZACAO,
            ],
            [
                'status' => EquipmentMovement::STATUS_AGUARDANDO_VISTORIA,
                'scheduled_at' => $amanha8h,
            ]
        );

        // AgendaCampo/relatorios ainda leem scheduled_at direto da O.S. --
        // mantem os dois preenchidos, cada widget alimentado pela fonte certa.
        if (! $scaniaOs->scheduled_at) {
            $scaniaOs->update(['scheduled_at' => $amanha8h]);
        }
    }

    /**
     * MaintenanceOrder::create() direto (como o resto deste seeder faz) nao
     * passa por logStatusChange()/updateStatus(), entao maintenance_status_histories
     * fica vazio -- sem registro de quem/quando assumiu a O.S. Preenche isso
     * uma vez so (idempotente).
     */
    private function ensureAtribuicaoHistorico(MaintenanceOrder $scaniaOs, User $tecnico): void
    {
        if ($scaniaOs->statusHistories()->exists()) {
            return;
        }

        $scaniaOs->logStatusChange(
            $scaniaOs->internal_status,
            null,
            "Ordem atribuída ao técnico {$tecnico->name} para liberação do equipamento.",
            $tecnico->id
        );
    }

    /**
     * Setor "Logística" + perfil "Supervisor de Pátio" vinculado a ele
     * (roles.department_id, ver RoleResource) + 1 usuario de teste -- pra
     * validar a visibilidade por setor da Programacao sem precisar montar
     * isso manualmente na UI a cada teste.
     */
    private function seedSetorTesteSupervisao(Tenant $tenant): void
    {
        $departamento = Department::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'LOG'],
            ['name' => 'Logística']
        );

        $role = Role::firstOrCreate(
            ['name' => 'Supervisor de Pátio', 'guard_name' => 'web', 'tenant_id' => $tenant->id]
        );
        if ($role->department_id !== $departamento->id) {
            $role->update(['department_id' => $departamento->id]);
        }

        $supervisor = User::firstOrCreate(
            ['email' => 'supervisor@geradoresrmc.com.br'],
            [
                'name' => 'Supervisor de Pátio RMC',
                'password' => Hash::make('Demo@Oravel1'),
                'tenant_id' => $tenant->id,
                'role' => 'supervisor',
                'department_id' => $departamento->id,
            ]
        );
        $supervisor->forceFill(['email_verified_at' => now()])->save();
        if (! $supervisor->hasRole($role)) {
            $supervisor->assignRole($role);
        }
    }
}
