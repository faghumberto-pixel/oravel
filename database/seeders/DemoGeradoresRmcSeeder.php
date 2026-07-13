<?php

namespace Database\Seeders;

use App\Models\AbcMatrix;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\ChecklistGroup;
use App\Models\Client;
use App\Models\Contract;
use App\Models\CriticalityLevel;
use App\Models\Department;
use App\Models\EquipmentDamage;
use App\Models\EquipmentDamageFollowUp;
use App\Models\EquipmentMovement;
use App\Models\EquipmentReplacement;
use App\Models\FleetVehicle;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use App\Models\MaintenanceOrderMaterial;
use App\Models\MaintenancePlan;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\PartsRequest;
use App\Models\Plan;
use App\Models\PreventiveMaintenanceExecution;
use App\Models\Role;
use App\Models\SolicitacaoLocacao;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\TenantProvisioner;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

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

    private ?Generator $faker = null;

    /** @var array<int, User> */
    private array $technicianPool = [];

    /** @var array<int, User> */
    private array $userPool = [];

    /** Templates de manutencao preventiva do grupo "Geradores", mesmo texto do PreventiveMaintenanceTemplateSeeder. */
    private const PREVENTIVE_ITEMS = [
        ['name' => 'Troca de óleo do motor', 'interval_hours' => 250],
        ['name' => 'Troca de filtro de óleo', 'interval_hours' => 250],
        ['name' => 'Troca de filtro de ar', 'interval_hours' => 500],
        ['name' => 'Troca de filtro de combustível', 'interval_hours' => 500],
        ['name' => 'Verificação/troca de correias', 'interval_hours' => 1000],
        ['name' => 'Análise de fluido de arrefecimento', 'interval_hours' => 1000],
        ['name' => 'Teste de bateria', 'interval_hours' => 500],
    ];

    /** Geradores extras "de vitrine" -- volume/variedade alem do trio original (Cummins/MWM/Scania). */
    private const ASSET_DEFS = [
        ['tag' => 'GER-PERKINS-100', 'name' => 'Gerador Perkins 100 kVA', 'capacity_value' => 100, 'status' => Asset::STATUS_DISPONIVEL, 'veterano' => false],
        ['tag' => 'GER-VOLVO-350', 'name' => 'Gerador Volvo Penta 350 kVA', 'capacity_value' => 350, 'status' => Asset::STATUS_LOCADO, 'veterano' => false],
        ['tag' => 'GER-STEMAC-60', 'name' => 'Gerador Stemac 60 kVA', 'capacity_value' => 60, 'status' => Asset::STATUS_DISPONIVEL, 'veterano' => true],
        ['tag' => 'GER-CAT-750', 'name' => 'Gerador Caterpillar 750 kVA', 'capacity_value' => 750, 'status' => Asset::STATUS_LOCADO, 'veterano' => true],
        ['tag' => 'GER-WEG-80', 'name' => 'Gerador Weg 80 kVA', 'capacity_value' => 80, 'status' => Asset::STATUS_MANUTENCAO, 'veterano' => false],
        ['tag' => 'GER-CUMMINS-220', 'name' => 'Gerador Cummins 220 kVA', 'capacity_value' => 220, 'status' => Asset::STATUS_DISPONIVEL, 'veterano' => false],
        ['tag' => 'GER-MWM-130', 'name' => 'Gerador MWM 130 kVA', 'capacity_value' => 130, 'status' => Asset::STATUS_AGUARDANDO_TRIAGEM, 'veterano' => false],
        ['tag' => 'GER-PERKINS-400', 'name' => 'Gerador Perkins 400 kVA', 'capacity_value' => 400, 'status' => Asset::STATUS_LOCADO, 'veterano' => true],
    ];

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

        $this->userPool = [
            User::where('tenant_id', $tenant->id)->where('email', 'admin@geradoresrmc.com.br')->first(),
            User::where('tenant_id', $tenant->id)->where('email', 'supervisor@geradoresrmc.com.br')->first(),
        ];
        $this->technicianPool = [$tecnico];

        $tecnicoRole = Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $this->seedEnrichment($tenant, $tecnicoRole, $cliente);

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

    /**
     * Bloco de volume/variedade realista, escopado 100% ao Geradores RMC --
     * mesmo padrao do TorresGuindastesDemoSeeder::seedEnrichment(). Guarda
     * de idempotencia: se o tenant ja tem 8+ ativos, ja rodou antes (o trio
     * original Cummins/MWM/Scania fica de fora dessa contagem por design --
     * 3 originais + 8 novos = 11).
     */
    private function seedEnrichment(Tenant $tenant, Role $tecnicoRole, Client $baseClient): void
    {
        if (Asset::where('tenant_id', $tenant->id)->count() >= 8) {
            $this->command?->info('Geradores RMC: enriquecimento já rodou antes -- pulando.');

            return;
        }

        $this->command?->info('Geradores RMC: iniciando enriquecimento com dados de volume...');

        DB::transaction(function () use ($tenant, $tecnicoRole, $baseClient) {
            $departments = $this->seedDepartments($tenant);
            $this->seedExtraUsers($tenant, $departments, $tecnicoRole);
            $this->seedPreventiveTemplates($tenant);

            $clients = $this->seedClients($tenant, $baseClient);
            $assets = $this->seedExtraAssets($tenant);
            $this->seedContracts($tenant, $assets, $clients);
            $orders = $this->seedMaintenanceOrdersVolume($tenant, $assets, $clients);
            $this->seedEquipmentMovementsVolume($tenant, $orders);
            $this->seedEquipmentReplacementsVolume($tenant, $orders);
            $materials = $this->seedMaterialsVolume($tenant);
            $this->seedSuppliers($tenant, $materials);
            $this->seedPartsRequestsVolume($tenant, $orders, $materials);
            $this->seedSolicitacoesLocacao($tenant, $clients, $assets);
            $this->seedAbcMatrix($tenant, $assets);
            $this->seedPreventiveMaintenanceExecutions($tenant, $assets, $orders);
            $this->seedUserActivityLogs($tenant);
        });

        $this->command?->info('Geradores RMC: enriquecimento concluído.');
    }

    private function faker(): Generator
    {
        return $this->faker ??= FakerFactory::create(config('app.faker_locale', 'pt_BR'));
    }

    /** @return array<string, Department> */
    private function seedDepartments(Tenant $tenant): array
    {
        $defs = [
            'manutencao' => ['name' => 'Manutenção', 'code' => 'MANUT'],
            'administrativo' => ['name' => 'Administrativo/Financeiro', 'code' => 'ADM'],
        ];

        $departments = [];
        foreach ($defs as $key => $def) {
            $departments[$key] = Department::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $def['code']],
                ['name' => $def['name']]
            );
        }

        return $departments;
    }

    /**
     * Mesmo padrao do Torres: role "Supervisor de Manutenção" vinculada ao
     * Setor Manutenção (demonstra a visibilidade por setor da Programacao
     * num segundo perfil, alem do "Supervisor de Pátio" ja criado), 1
     * usuario "Comercial" (permissoes de Solicitacao de Locacao) e mais 2
     * tecnicos.
     */
    private function seedExtraUsers(Tenant $tenant, array $departments, Role $tecnicoRole): void
    {
        $supervisorManutRole = Role::firstOrCreate(
            ['name' => 'Supervisor de Manutenção', 'guard_name' => 'web', 'tenant_id' => $tenant->id],
            ['department_id' => $departments['manutencao']->id]
        );
        if ($supervisorManutRole->department_id !== $departments['manutencao']->id) {
            $supervisorManutRole->update(['department_id' => $departments['manutencao']->id]);
        }

        $supervisorName = $this->fakeName();
        $supervisorManut = $this->createUser(
            $tenant,
            $supervisorName,
            strtolower(Str::slug($supervisorName, '.')).'@geradoresrmc.com.br',
            $departments['manutencao']->id
        );
        $supervisorManut->assignRole($supervisorManutRole);
        $this->userPool[] = $supervisorManut;

        $comercialRole = Role::firstOrCreate(['name' => 'Comercial', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        foreach (['ler_solicitacao_locacao', 'criar_solicitacao_locacao', 'editar_solicitacao_locacao', 'excluir_solicitacao_locacao'] as $permName) {
            $comercialRole->givePermissionTo(Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']));
        }

        $comercialName = $this->fakeName();
        $comercial = $this->createUser(
            $tenant,
            $comercialName,
            strtolower(Str::slug($comercialName, '.')).'@geradoresrmc.com.br',
            $departments['administrativo']->id
        );
        $comercial->assignRole($comercialRole);
        $this->userPool[] = $comercial;

        for ($i = 0; $i < 2; $i++) {
            $name = $this->fakeName();
            $user = $this->createUser(
                $tenant,
                $name,
                strtolower(Str::slug($name, '.')).'@geradoresrmc.com.br',
                $departments['manutencao']->id
            );
            $user->assignRole($tecnicoRole);
            $this->technicianPool[] = $user;
            $this->userPool[] = $user;
        }
    }

    private function createUser(Tenant $tenant, string $name, string $email, string $departmentId): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('Demo@Oravel1'),
                'tenant_id' => $tenant->id,
                'department_id' => $departmentId,
                'hourly_rate' => $this->faker()->randomFloat(2, 25, 90),
            ]
        );
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function fakeName(): string
    {
        return $this->faker()->unique()->name();
    }

    private function seedPreventiveTemplates(Tenant $tenant): void
    {
        $group = ChecklistGroup::where('tenant_id', $tenant->id)->where('name', 'Geradores')->first();
        if (! $group) {
            return;
        }

        foreach (self::PREVENTIVE_ITEMS as $item) {
            MaintenancePlan::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'checklist_group_id' => $group->id,
                    'name' => $item['name'],
                ],
                [
                    'interval_hours' => $item['interval_hours'],
                    'is_active' => true,
                ]
            );
        }
    }

    /** @return Collection<int, Client> */
    private function seedClients(Tenant $tenant, Client $baseClient): Collection
    {
        $names = [
            'Construtora Vetor Campinas',
            'Hospital Regional São Vicente',
            'Data Center Interclouds SP',
            'Condomínio Industrial Anhanguera',
            'Agroindústria Vale do Sol',
        ];

        foreach ($names as $name) {
            Client::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $name]);
        }

        return Client::where('tenant_id', $tenant->id)->get()->push($baseClient)->unique('id')->values();
    }

    /** @return Collection<int, Asset> */
    private function seedExtraAssets(Tenant $tenant): Collection
    {
        $category = AssetCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Gerador']);
        $group = ChecklistGroup::where('tenant_id', $tenant->id)->where('name', 'Geradores')->first();

        $assets = collect();

        foreach (self::ASSET_DEFS as $def) {
            $asset = Asset::firstOrCreate(
                ['tenant_id' => $tenant->id, 'tag' => $def['tag']],
                [
                    'name' => $def['name'],
                    'patrimonio' => 'PAT'.$this->faker()->unique()->numerify('######'),
                    'serial_number' => strtoupper($this->faker()->bothify('??########')),
                    'status' => $def['status'],
                    'criticality' => $def['veterano'] ? 'high' : 'medium',
                    'criticality_level' => $def['veterano'] ? 'Alta' : 'Média',
                    'asset_category' => $category->name,
                    'checklist_group_id' => $group?->id,
                    'capacity_value' => $def['capacity_value'],
                    'capacity_unit' => 'kVA',
                    'acquisition_value' => $this->faker()->randomFloat(2, 40000, 500000),
                    'acquisition_date' => $def['veterano']
                        ? $this->faker()->dateTimeBetween('-8 years', '-4 years')->format('Y-m-d')
                        : $this->faker()->dateTimeBetween('-3 years', '-2 months')->format('Y-m-d'),
                    'manufacturing_year' => $def['veterano']
                        ? $this->faker()->numberBetween(2014, 2019)
                        : $this->faker()->numberBetween(2020, 2025),
                    'horimetro_atual' => $def['veterano']
                        ? $this->faker()->randomFloat(2, 6000, 15000)
                        : $this->faker()->randomFloat(2, 100, 3000),
                    'last_horimetro' => 0,
                ]
            );

            $assets->push($asset);
        }

        return $assets;
    }

    private function seedContracts(Tenant $tenant, Collection $assets, Collection $clients): void
    {
        $locados = $assets->where('status', Asset::STATUS_LOCADO)->values();

        foreach ($locados as $asset) {
            if (! $asset->client_id) {
                $asset->update(['client_id' => $clients->random()->id]);
            }

            Contract::factory()->create([
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'client_id' => $asset->client_id,
            ]);
        }

        $disponiveis = $assets->where('status', Asset::STATUS_DISPONIVEL)->values();
        foreach ($disponiveis->take(2) as $asset) {
            Contract::factory()->rascunho()->create([
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'client_id' => $clients->random()->id,
            ]);
        }
        foreach ($disponiveis->skip(2)->take(1) as $asset) {
            Contract::factory()->encerrado()->create([
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'client_id' => $clients->random()->id,
            ]);
        }
    }

    /** @return Collection<int, MaintenanceOrder> */
    private function seedMaintenanceOrdersVolume(Tenant $tenant, Collection $assets, Collection $clients): Collection
    {
        $ranges = [
            'aguardandoDiagnostico' => [2, 3],
            'emManutencao' => [2, 3],
            'aguardandoPeca' => [1, 2],
            'testeQualidade' => [1, 2],
            'pendencia' => [1, 2],
            'concluido' => [2, 4],
            'cancelada' => [0, 1],
        ];

        $orders = collect();

        foreach ($ranges as $state => $range) {
            $count = $this->faker()->numberBetween($range[0], $range[1]);

            for ($i = 0; $i < $count; $i++) {
                $asset = $assets->random();
                $technician = collect($this->technicianPool)->random();

                /** @var MaintenanceOrder $order */
                $order = MaintenanceOrder::factory()->{$state}()->create([
                    'tenant_id' => $tenant->id,
                    'asset_id' => $asset->id,
                    'technician_id' => $technician->id,
                    'client_id' => $asset->client_id ?: $clients->random()->id,
                ]);

                $orders->push($order);

                if (in_array($state, ['testeQualidade', 'concluido'], true)) {
                    MaintenanceOrderChecklist::factory()
                        ->count($this->faker()->numberBetween(2, 4))
                        ->completo()
                        ->create(['tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id]);
                } elseif (in_array($state, ['emManutencao', 'aguardandoPeca'], true)) {
                    MaintenanceOrderChecklist::factory()
                        ->count($this->faker()->numberBetween(1, 3))
                        ->create(['tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id]);
                }

                if ($state === 'aguardandoPeca') {
                    MaintenanceOrderMaterial::factory()
                        ->count($this->faker()->numberBetween(1, 2))
                        ->create(['tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id]);
                }
            }
        }

        return $orders;
    }

    /** @return Collection<int, EquipmentMovement> */
    private function seedEquipmentMovementsVolume(Tenant $tenant, Collection $orders): Collection
    {
        $movements = collect();

        if ($orders->isEmpty()) {
            return $movements;
        }

        $states = [
            'aguardandoVistoria' => $this->faker()->numberBetween(1, 3),
            'emAndamento' => $this->faker()->numberBetween(1, 2),
            'concluido' => $this->faker()->numberBetween(1, 2),
        ];
        $stateMethods = ['emAndamento' => 'emAndamento', 'concluido' => 'concluido'];

        foreach ($states as $stateKey => $count) {
            for ($i = 0; $i < $count; $i++) {
                $order = $orders->random();
                $factory = EquipmentMovement::factory();
                if (isset($stateMethods[$stateKey])) {
                    $factory = $factory->{$stateMethods[$stateKey]}();
                }

                /** @var EquipmentMovement $movement */
                $movement = $factory->create([
                    'tenant_id' => $tenant->id,
                    'maintenance_order_id' => $order->id,
                    'asset_id' => $order->asset_id,
                ]);
                $movements->push($movement);

                if ($stateKey !== 'aguardandoVistoria' && $this->faker()->boolean(30)) {
                    $reporter = collect($this->technicianPool)->random();

                    $damageFactory = EquipmentDamage::factory();
                    $emCobranca = $this->faker()->boolean(50);
                    $damageFactory = $emCobranca ? $damageFactory->emCobranca() : $damageFactory->resolvido();

                    /** @var EquipmentDamage $damage */
                    $damage = $damageFactory->create([
                        'tenant_id' => $tenant->id,
                        'equipment_movement_id' => $movement->id,
                        'maintenance_order_id' => $order->id,
                        'asset_id' => $order->asset_id,
                        'reported_by_user_id' => $reporter->id,
                    ]);

                    if ($emCobranca) {
                        EquipmentDamageFollowUp::factory()
                            ->count($this->faker()->numberBetween(1, 2))
                            ->create([
                                'tenant_id' => $tenant->id,
                                'equipment_damage_id' => $damage->id,
                                'user_id' => $reporter->id,
                            ]);
                    }
                }
            }
        }

        return $movements;
    }

    /**
     * Cenarios de troca de equipamento, mesmo padrao do Torres (escopado
     * so a esse tenant, nao itera Tenant::all() como o
     * EquipmentReplacementDemoSeeder original faz).
     */
    private function seedEquipmentReplacementsVolume(Tenant $tenant, Collection $orders): void
    {
        $ordersWithTech = $orders->filter(fn (MaintenanceOrder $o) => $o->technician_id && $o->asset_id)->values();

        if ($ordersWithTech->isEmpty()) {
            return;
        }

        $availableAssets = Asset::where('tenant_id', $tenant->id)
            ->where('status', Asset::STATUS_DISPONIVEL)
            ->inRandomOrder()
            ->get();

        $commercialRole = Role::where('name', EquipmentDamage::ROLE_COMERCIAL)
            ->where('guard_name', 'web')
            ->where('tenant_id', $tenant->id)
            ->first();
        $commercialUser = $commercialRole
            ? User::role($commercialRole)->where('tenant_id', $tenant->id)->first()
            : null;

        $swapAsset = $availableAssets->shift();
        $sharedAsset = $availableAssets->first();

        $this->createReplacementRequest($tenant, $ordersWithTech->random());

        if ($commercialUser && $sharedAsset) {
            $replacement = $this->createReplacementRequest($tenant, $ordersWithTech->random());
            $replacement->identifyReplacement($sharedAsset, $commercialUser);
        }

        if ($commercialUser && $sharedAsset) {
            $replacement = $this->createReplacementRequest($tenant, $ordersWithTech->random());
            $replacement->identifyReplacement($sharedAsset, $commercialUser);
            $replacement->startLogisticsMovements();
        }

        if ($commercialUser && $swapAsset) {
            $orderForSwap = $this->findOrderWithActiveContract($tenant, $ordersWithTech) ?? $ordersWithTech->random();

            $replacement = $this->createReplacementRequest($tenant, $orderForSwap);
            $replacement->identifyReplacement($swapAsset, $commercialUser);
            $replacement->startLogisticsMovements();
            $replacement->desmobilizationMovement->update([
                'status' => EquipmentMovement::STATUS_CONCLUIDO,
                'completed_at' => now()->subDays(5),
            ]);
            $replacement->mobilizationMovement->update([
                'status' => EquipmentMovement::STATUS_CONCLUIDO,
                'completed_at' => now()->subDay(),
                'client_signature' => 'data:image/png;base64,'.base64_encode('demo-signature'),
            ]);
        }

        $cancelledOrder = $ordersWithTech->random();
        EquipmentReplacement::factory()->cancelado()->create([
            'tenant_id' => $tenant->id,
            'maintenance_order_id' => $cancelledOrder->id,
            'original_asset_id' => $cancelledOrder->asset_id,
            'requested_by_user_id' => $cancelledOrder->technician_id,
        ]);

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
    }

    private function createReplacementRequest(Tenant $tenant, MaintenanceOrder $order): EquipmentReplacement
    {
        return EquipmentReplacement::factory()->create([
            'tenant_id' => $tenant->id,
            'maintenance_order_id' => $order->id,
            'original_asset_id' => $order->asset_id,
            'requested_by_user_id' => $order->technician_id,
        ]);
    }

    private function findOrderWithActiveContract(Tenant $tenant, Collection $orders): ?MaintenanceOrder
    {
        $activeContractAssetIds = Contract::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->pluck('asset_id');

        return $orders->first(fn (MaintenanceOrder $order) => $activeContractAssetIds->contains($order->asset_id));
    }

    /** @return Collection<int, Material> */
    private function seedMaterialsVolume(Tenant $tenant): Collection
    {
        $materials = collect();
        $categoryDefs = ['Motor', 'Elétrica', 'Painel de Controle', 'Consumíveis'];
        $categories = [];
        foreach ($categoryDefs as $name) {
            $categories[] = MaterialCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $name]);
        }

        $partNames = [
            'Filtro de Óleo', 'Filtro de Ar', 'Correia do Alternador', 'Bateria 12V 100Ah',
            'Vela de Aquecimento', 'Sensor de Temperatura', 'Bomba de Água', 'Radiador',
            'Módulo de Controle (ECU)', 'Disjuntor de Proteção', 'Bico Injetor', 'Retentor de Óleo',
        ];

        foreach ($partNames as $i => $name) {
            $belowMin = $this->faker()->boolean(20);
            $minStock = $this->faker()->numberBetween(3, 15);

            $materials->push(Material::firstOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => 'RMC-'.strtoupper(Str::random(6))],
                [
                    'name' => $name,
                    'material_category_id' => $categories[$i % count($categories)]->id,
                    'unit_cost' => $this->faker()->randomFloat(2, 30, 900),
                    'price' => $this->faker()->randomFloat(2, 50, 1400),
                    'min_stock' => $minStock,
                    'max_stock' => $minStock * 5,
                    'current_stock' => $belowMin ? $this->faker()->numberBetween(0, max($minStock - 1, 0)) : $this->faker()->numberBetween($minStock, $minStock * 4),
                ]
            ));
        }

        return $materials;
    }

    private function seedSuppliers(Tenant $tenant, Collection $materials): void
    {
        $suppliers = Supplier::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        if ($suppliers->isEmpty() || $materials->isEmpty()) {
            return;
        }

        foreach ($materials as $i => $material) {
            if ($i % 4 !== 3) {
                $material->update(['supplier_id' => $suppliers[$i % $suppliers->count()]->id]);
            }
        }
    }

    private function seedPartsRequestsVolume(Tenant $tenant, Collection $orders, Collection $materials): void
    {
        if ($orders->isEmpty() || $materials->isEmpty()) {
            return;
        }

        $plan = ['pendente' => 2, 'pedida' => 2, 'entregue' => 2];

        foreach ($plan as $state => $count) {
            for ($i = 0; $i < $count; $i++) {
                $factory = PartsRequest::factory();
                if ($state !== 'pendente') {
                    $factory = $factory->{$state}();
                }

                $factory->create([
                    'tenant_id' => $tenant->id,
                    'maintenance_order_id' => $orders->random()->id,
                    'material_id' => $materials->random()->id,
                ]);
            }
        }
    }

    private function seedSolicitacoesLocacao(Tenant $tenant, Collection $clients, Collection $assets): void
    {
        $categories = AssetCategory::where('tenant_id', $tenant->id)->get();
        if ($categories->isEmpty() || empty($this->userPool)) {
            return;
        }

        $plan = ['base' => 2, 'fechada' => 1, 'cancelada' => 1];

        foreach ($plan as $state => $count) {
            for ($i = 0; $i < $count; $i++) {
                $factory = SolicitacaoLocacao::factory();
                if ($state !== 'base') {
                    $factory = $factory->{$state}();
                }

                // "Fechar o contrato" exige o ativo disponivel de verdade
                // (SolicitacaoLocacao::booted() valida isso no saving()) --
                // le do banco na hora, nao da colecao $assets capturada
                // antes de seedContracts/seedEquipmentReplacementsVolume
                // rodarem (aquele fluxo troca o status real de alguns ativos).
                $assetId = $state === 'fechada'
                    ? Asset::where('tenant_id', $tenant->id)->where('status', Asset::STATUS_DISPONIVEL)->inRandomOrder()->value('id')
                    : ($this->faker()->boolean(60) ? $assets->random()->id : null);

                $factory->create([
                    'tenant_id' => $tenant->id,
                    'user_id' => collect($this->userPool)->random()->id,
                    'customer_id' => $clients->random()->id,
                    'category_id' => $categories->random()->id,
                    'asset_id' => $assetId,
                ]);
            }
        }
    }

    private function seedAbcMatrix(Tenant $tenant, Collection $assets): void
    {
        foreach ($assets as $asset) {
            [$nivel, $descricao] = match ($asset->criticality) {
                'high' => ['A', 'Ativo crítico -- alta receita e alto impacto operacional se indisponível'],
                'medium' => ['B', 'Ativo relevante -- impacto moderado se indisponível'],
                default => ['C', 'Ativo de apoio -- baixo impacto se indisponível'],
            };

            AbcMatrix::firstOrCreate(
                ['tenant_id' => $tenant->id, 'asset_id' => $asset->id],
                ['nivel' => $nivel, 'descricao' => $descricao]
            );
        }
    }

    private function seedPreventiveMaintenanceExecutions(Tenant $tenant, Collection $assets, Collection $orders): void
    {
        $preventiveOrders = $orders->filter(fn (MaintenanceOrder $o) => $o->maintenance_type === MaintenanceOrder::TYPE_PREVENTIVE)->values();
        if ($preventiveOrders->isEmpty()) {
            return;
        }

        foreach ($preventiveOrders->take(5) as $order) {
            $asset = $assets->firstWhere('id', $order->asset_id);
            if (! $asset || ! $asset->checklist_group_id) {
                continue;
            }

            $planTemplate = MaintenancePlan::where('tenant_id', $tenant->id)
                ->where('checklist_group_id', $asset->checklist_group_id)
                ->inRandomOrder()
                ->first();

            if (! $planTemplate) {
                continue;
            }

            PreventiveMaintenanceExecution::create([
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'maintenance_plan_id' => $planTemplate->id,
                'maintenance_order_id' => $order->id,
                'technician_id' => $order->technician_id,
                'horimetro_at_execution' => $asset->horimetro_atual,
                'observacao' => 'Manutenção preventiva realizada conforme plano '.$planTemplate->name,
            ]);
        }
    }

    private function seedUserActivityLogs(Tenant $tenant): void
    {
        foreach ($this->userPool as $user) {
            UserActivityLog::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'method' => 'POST',
                'path' => '/admin/login',
                'route_name' => 'filament.admin.auth.login',
                'resource_label' => 'Login',
                'action' => UserActivityLog::ACTION_LOGIN,
            ]);
        }

        $viewedResources = ['Ativos', 'Ordens de Manutenção', 'Solicitações de Locação'];
        foreach ($viewedResources as $resourceLabel) {
            UserActivityLog::create([
                'tenant_id' => $tenant->id,
                'user_id' => collect($this->userPool)->random()->id,
                'method' => 'GET',
                'path' => '/admin',
                'route_name' => 'filament.admin.pages.dashboard',
                'resource_label' => $resourceLabel,
                'action' => UserActivityLog::ACTION_VIEW,
            ]);
        }
    }
}
