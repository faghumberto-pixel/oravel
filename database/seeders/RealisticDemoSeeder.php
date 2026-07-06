<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\Contract;
use App\Models\CriticalityLevel;
use App\Models\Department;
use App\Models\EquipmentDamage;
use App\Models\EquipmentDamageFollowUp;
use App\Models\EquipmentMovement;
use App\Models\FleetMaintenancePlan;
use App\Models\FleetVehicle;
use App\Models\FreightCarrier;
use App\Models\FreightRecord;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use App\Models\MaintenanceOrderMaterial;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\PartsRequest;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SolicitacaoLocacao;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Popula o banco de DEV com dados realistas de uma locadora de equipamentos
 * (multi-tenant), para demonstracao/teste com volume e variedade real.
 *
 * NUNCA rodar em producao -- guard abaixo aborta se o ambiente for 'production'.
 * Idempotente por tenant: se o slug ja existe, pula aquele tenant inteiro
 * (rodar de novo por engano nao duplica nada).
 *
 * Uso: php artisan db:seed --class=RealisticDemoSeeder
 *
 * Fora de escopo nesta rodada (tabelas quebradas/inexistentes, ver auditoria):
 * AccountPayable/BillCategory/CostCenter (tabelas nao existem).
 */
class RealisticDemoSeeder extends Seeder
{
    private array $technicianPool = [];

    private array $userPool = [];

    private array $assetCategoryNames = ['Gerador', 'Plataforma Elevatória', 'Prensa Hidráulica', 'Empilhadeira', 'Compressor de Ar'];

    public function run(): void
    {
        if (app()->environment('production')) {
            abort(1, 'RealisticDemoSeeder nunca deve rodar em producao.');
        }

        $plans = $this->createPlans();

        foreach ($this->tenantBlueprints($plans) as $blueprint) {
            $this->seedTenant($blueprint);
        }
    }

    /**
     * As 28 chaves reais do SaaSRegistry, variando cobertura por plano.
     */
    private function createPlans(): array
    {
        $allFeatures = [
            'tabela_abc_matrix', 'tabela_account_payables', 'tabela_assets', 'tabela_asset_categories',
            'tabela_bill_categories', 'tabela_branches', 'modulo_chat', 'tabela_checklist_groups',
            'tabela_checklist_templates', 'tabela_clients', 'tabela_companies', 'tabela_contracts',
            'tabela_cost_centers', 'tabela_departments', 'tabela_equipment_damages',
            'tabela_equipment_movements', 'tabela_fleet_statuses', 'tabela_internal_units',
            'tabela_locations', 'tabela_maintenance_orders', 'tabela_maintenance_plans',
            'tabela_materials', 'tabela_material_categories', 'tabela_parts_requests',
            'tabela_roles', 'tabela_solicitacao_locacao', 'tabela_suppliers', 'tabela_users',
            'tabela_fleet_vehicles', 'tabela_freight_carriers', 'tabela_freight_records',
            'tabela_fleet_maintenance_plans', 'tabela_user_activity_logs',
            'tabela_equipment_replacements',
        ];

        $basicKeys = [
            'tabela_assets', 'tabela_asset_categories', 'tabela_clients', 'tabela_contracts',
            'tabela_departments', 'tabela_maintenance_orders', 'tabela_roles', 'tabela_users',
            'tabela_user_activity_logs',
        ];

        $standardKeys = array_merge($basicKeys, [
            'tabela_materials', 'tabela_material_categories', 'tabela_suppliers',
            'tabela_parts_requests', 'tabela_solicitacao_locacao', 'tabela_equipment_movements',
            'tabela_equipment_damages', 'tabela_checklist_templates', 'tabela_checklist_groups',
            'tabela_equipment_replacements', 'modulo_chat',
        ]);

        $plans = [];

        $plans['BASIC'] = Plan::firstOrCreate(
            ['name' => 'BASIC'],
            [
                'price' => 499, 'base_price' => 499, 'level' => 1, 'billing_cycle' => 'monthly',
                'is_active' => true, 'features' => array_fill_keys($basicKeys, true),
            ]
        );

        $plans['STANDARD'] = Plan::firstOrCreate(
            ['name' => 'STANDARD'],
            [
                'price' => 999, 'base_price' => 999, 'level' => 2, 'billing_cycle' => 'monthly',
                'is_active' => true, 'features' => array_fill_keys($standardKeys, true),
            ]
        );

        $plans['PREMIUM'] = Plan::firstOrCreate(
            ['name' => 'PREMIUM'],
            [
                'price' => 1999, 'base_price' => 1999, 'level' => 3, 'billing_cycle' => 'monthly',
                'is_active' => true, 'features' => array_fill_keys($allFeatures, true),
            ]
        );

        return $plans;
    }

    private function tenantBlueprints(array $plans): array
    {
        return [
            ['name' => 'Vale Forte Construções e Locação', 'slug' => 'vale-forte-locacao', 'segment' => 'Construção Civil', 'plan' => $plans['PREMIUM']],
            ['name' => 'Horizonte Eventos & Estruturas', 'slug' => 'horizonte-eventos', 'segment' => 'Eventos', 'plan' => $plans['STANDARD']],
            ['name' => 'Serra Alta Mineração', 'slug' => 'serra-alta-mineracao', 'segment' => 'Mineração', 'plan' => $plans['PREMIUM']],
            ['name' => 'Multiobras Engenharia', 'slug' => 'multiobras-engenharia', 'segment' => 'Construção Civil', 'plan' => $plans['BASIC']],
            ['name' => 'Cascavel Equipamentos Industriais', 'slug' => 'cascavel-industrial', 'segment' => 'Industrial/Metalurgia', 'plan' => $plans['STANDARD']],
            ['name' => 'Rota Norte Empreendimentos', 'slug' => 'rota-norte-empreendimentos', 'segment' => 'Construção Civil', 'plan' => $plans['PREMIUM']],
            ['name' => 'Planalto Eventos Corporativos', 'slug' => 'planalto-eventos', 'segment' => 'Eventos', 'plan' => $plans['BASIC']],
            ['name' => 'Pedra Forte Mineração', 'slug' => 'pedra-forte-mineracao', 'segment' => 'Mineração', 'plan' => $plans['PREMIUM']],
        ];
    }

    private function seedTenant(array $bp): void
    {
        if (Tenant::where('slug', $bp['slug'])->exists()) {
            $this->command?->info("Tenant '{$bp['slug']}' já existe -- pulando (idempotente).");

            return;
        }

        $this->command?->info("Semeando tenant '{$bp['name']}'...");

        $this->technicianPool = [];
        $this->userPool = [];

        DB::transaction(function () use ($bp) {
            $tenant = Tenant::create([
                'name' => $bp['name'],
                'slug' => $bp['slug'],
                'status' => 'active',
                'plan_id' => $bp['plan']->id,
                'onboarding_completed' => true,
            ]);

            $adminEmail = 'admin@'.$bp['slug'].'.com.br';
            TenantProvisioner::provision($tenant, [
                'name' => 'Administrador '.$bp['name'],
                'email' => $adminEmail,
                'password' => 'demo12345',
            ]);

            $departments = $this->seedDepartments($tenant);
            $this->seedUsers($tenant, $departments, $bp['slug']);

            $assetCategories = $this->seedAssetCategories($tenant);
            $this->seedCriticalityLevels($tenant);
            $clients = Client::factory()->count(7)->create(['tenant_id' => $tenant->id]);
            $assets = $this->seedAssets($tenant, $clients);
            $this->seedContracts($tenant, $assets, $clients);
            $maintenanceOrders = $this->seedMaintenanceOrders($tenant, $assets, $clients);
            $equipmentMovements = $this->seedEquipmentMovements($tenant, $assets, $maintenanceOrders);
            $materials = $this->seedMaterials($tenant);
            $this->seedSolicitacoesLocacao($tenant, $clients, $assetCategories, $assets);

            // Fornecedores/Pecas so existem a partir do plano STANDARD (ver
            // $standardKeys em createPlans()); frota so no PREMIUM.
            if ($tenant->hasFeature('tabela_suppliers')) {
                $this->seedSuppliers($tenant, $materials);
            }
            if ($tenant->hasFeature('tabela_parts_requests')) {
                $this->seedPartsRequests($tenant, $maintenanceOrders, $materials);
            }
            if ($tenant->hasFeature('tabela_fleet_vehicles')) {
                $this->seedFleet($tenant, $equipmentMovements);
            }
        });

        $this->command?->info("Tenant '{$bp['name']}' concluído.");
    }

    private function seedDepartments(Tenant $tenant): array
    {
        $defs = [
            'comercial' => ['name' => 'Comercial', 'code' => 'COM'],
            'manutencao' => ['name' => 'Manutenção', 'code' => 'MANUT'],
            'logistica' => ['name' => 'Logística', 'code' => 'LOG'],
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
     * Cria os papeis extras (alem de admin/Supervisor de Manutencao/Comercial/
     * Gerente de Manutencao, ja criados por TenantProvisioner::provision) e
     * distribui usuarios fictitios por eles. So cria as Roles -- sem
     * permissoes granulares (ja existem 4 esquemas conflitantes no codigo,
     * decisao explicita do usuario para nao adicionar um 5o agora).
     */
    private function seedUsers(Tenant $tenant, array $departments, string $slug): void
    {
        $roleDefs = [
            ['role' => 'Gerente Comercial', 'dept' => 'comercial', 'name' => $this->fakeName(), 'title' => 'gerente.comercial'],
            ['role' => 'Gerente de Manutenção', 'dept' => 'manutencao', 'name' => $this->fakeName(), 'title' => 'gerente.manutencao'],
            ['role' => 'Gerente de Logística', 'dept' => 'logistica', 'name' => $this->fakeName(), 'title' => 'gerente.logistica'],
            ['role' => 'Gerente Administrativo/Financeiro', 'dept' => 'administrativo', 'name' => $this->fakeName(), 'title' => 'gerente.admfin'],
            ['role' => 'Supervisor de Manutenção', 'dept' => 'manutencao', 'name' => $this->fakeName(), 'title' => 'supervisor.manutencao'],
            ['role' => 'Supervisor de Logística', 'dept' => 'logistica', 'name' => $this->fakeName(), 'title' => 'supervisor.logistica'],
            ['role' => 'Analista Financeiro', 'dept' => 'administrativo', 'name' => $this->fakeName(), 'title' => 'analista.financeiro'],
            ['role' => 'Analista de Compras', 'dept' => 'logistica', 'name' => $this->fakeName(), 'title' => 'analista.compras'],
            ['role' => 'Assistente Financeiro', 'dept' => 'administrativo', 'name' => $this->fakeName(), 'title' => 'assistente.financeiro'],
            ['role' => 'Assistente Administrativo', 'dept' => 'administrativo', 'name' => $this->fakeName(), 'title' => 'assistente.administrativo'],
        ];

        foreach ($roleDefs as $i => $def) {
            $role = Role::firstOrCreate(
                ['name' => $def['role'], 'guard_name' => 'web', 'tenant_id' => $tenant->id],
                ['department_id' => $departments[$def['dept']]->id]
            );

            $email = strtolower(Str::slug($def['name'], '.')).'@'.$slug.'.com.br';
            $user = $this->createUser($tenant, $def['name'], $email, $departments[$def['dept']]->id, 'colaborador');
            $user->assignRole($role);

            // Alem do cargo "de fachada" acima (organograma ficticio), da
            // tambem o papel base que TenantProvisioner::provision() ja cria
            // vazio pra todo tenant real -- e o nome que os Observers de
            // Avaria/Troca de Equipamento realmente checam pra notificar.
            if ($def['role'] === 'Gerente Comercial') {
                $baseRole = Role::firstOrCreate(
                    ['name' => EquipmentDamage::ROLE_COMERCIAL, 'guard_name' => 'web', 'tenant_id' => $tenant->id]
                );
                $user->assignRole($baseRole);
            }

            $this->userPool[] = $user;
        }

        $technicoRole = Role::firstOrCreate(
            ['name' => 'tecnico', 'guard_name' => 'web', 'tenant_id' => $tenant->id],
            ['department_id' => $departments['manutencao']->id]
        );

        for ($i = 0; $i < 4; $i++) {
            $name = $this->fakeName();
            $email = strtolower(Str::slug($name, '.')).'@'.$slug.'.com.br';
            $user = $this->createUser($tenant, $name, $email, $departments['manutencao']->id, 'tecnico');
            $user->assignRole($technicoRole);
            $this->technicianPool[] = $user;
            $this->userPool[] = $user;
        }
    }

    private function createUser(Tenant $tenant, string $name, string $email, string $departmentId, string $role): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('demo12345'),
            'tenant_id' => $tenant->id,
            'department_id' => $departmentId,
            'role' => $role,
            'hourly_rate' => $this->faker()->randomFloat(2, 25, 90),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function fakeName(): string
    {
        return $this->faker()->unique()->name();
    }

    private function faker(): Generator
    {
        static $faker;

        return $faker ??= Factory::create(config('app.faker_locale', 'pt_BR'));
    }

    private function seedAssetCategories(Tenant $tenant): array
    {
        $categories = [];
        foreach ($this->assetCategoryNames as $name) {
            $categories[] = AssetCategory::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name]
            );
        }

        return $categories;
    }

    private function seedCriticalityLevels(Tenant $tenant): void
    {
        $levels = [
            ['code' => 'baixa', 'name' => 'Baixa', 'color' => '#22c55e'],
            ['code' => 'media', 'name' => 'Média', 'color' => '#f59e0b'],
            ['code' => 'alta', 'name' => 'Alta', 'color' => '#ef4444'],
        ];

        foreach ($levels as $level) {
            CriticalityLevel::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $level['code']],
                ['name' => $level['name'], 'color' => $level['color']]
            );
        }
    }

    /** @return Collection<int, Asset> */
    private function seedAssets(Tenant $tenant, Collection $clients)
    {
        $veteranos = Asset::factory()->veterano()->count(6)->create(['tenant_id' => $tenant->id]);
        $novos = Asset::factory()->novo()->count(4)->create(['tenant_id' => $tenant->id]);
        $normais = Asset::factory()->count(10)->create(['tenant_id' => $tenant->id]);

        $assets = $veteranos->concat($novos)->concat($normais);

        // Uma parte fica "locada" com client_id preenchido, coerente com o status.
        $assets->where('status', 'locado')->each(function (Asset $asset) use ($clients) {
            $asset->update(['client_id' => $clients->random()->id]);
        });

        return $assets;
    }

    private function seedContracts(Tenant $tenant, Collection $assets, Collection $clients): void
    {
        $locados = $assets->where('status', 'locado')->values();

        foreach ($locados as $asset) {
            Contract::factory()->create([
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'client_id' => $asset->client_id ?? $clients->random()->id,
            ]);
        }

        // Alguns contratos extras em rascunho/encerrado, sobre ativos disponiveis.
        $disponiveis = $assets->where('status', '!=', 'locado')->values();
        foreach ($disponiveis->take(3) as $asset) {
            Contract::factory()->rascunho()->create([
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'client_id' => $clients->random()->id,
            ]);
        }
        foreach ($disponiveis->skip(3)->take(2) as $asset) {
            Contract::factory()->encerrado()->create([
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'client_id' => $clients->random()->id,
            ]);
        }
    }

    /**
     * Faixas por estado (nao contadores fixos) -- cada tenant sorteia sua
     * propria quantidade dentro da faixa, pra nao ficar com a mesma
     * distribuicao de status em todo tenant do banco de demonstracao.
     *
     * @return Collection<int, MaintenanceOrder>
     */
    private function seedMaintenanceOrders(Tenant $tenant, Collection $assets, Collection $clients)
    {
        $ranges = [
            'aguardandoDiagnostico' => [2, 6],
            'emManutencao' => [3, 8],
            'aguardandoPeca' => [2, 6],
            'testeQualidade' => [1, 5],
            'pendencia' => [1, 5],
            'concluido' => [3, 8],
            'cancelada' => [0, 2],
        ];

        $plan = array_map(
            fn (array $range) => $this->faker()->numberBetween($range[0], $range[1]),
            $ranges
        );

        $orders = collect();

        foreach ($plan as $state => $count) {
            for ($i = 0; $i < $count; $i++) {
                $asset = $assets->random();
                $technician = $this->technicianPool ? collect($this->technicianPool)->random() : null;

                /** @var MaintenanceOrder $order */
                $order = MaintenanceOrder::factory()->{$state}()->create([
                    'tenant_id' => $tenant->id,
                    'asset_id' => $asset->id,
                    'technician_id' => $technician?->id,
                    'client_id' => $asset->client_id,
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
    private function seedEquipmentMovements(Tenant $tenant, Collection $assets, Collection $maintenanceOrders): Collection
    {
        $movements = collect();

        if ($maintenanceOrders->isEmpty()) {
            return $movements;
        }

        // Faixas, nao contadores fixos -- mesmo motivo de seedMaintenanceOrders().
        $states = [
            'aguardandoVistoria' => $this->faker()->numberBetween(2, 6),
            'emAndamento' => $this->faker()->numberBetween(1, 5),
            'concluido' => $this->faker()->numberBetween(1, 5),
        ];
        $stateMethods = ['emAndamento' => 'emAndamento', 'concluido' => 'concluido'];

        foreach ($states as $stateKey => $count) {
            for ($i = 0; $i < $count; $i++) {
                $order = $maintenanceOrders->random();
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

                // ~30% das movimentacoes concluidas/em andamento tem avaria.
                if ($stateKey !== 'aguardandoVistoria' && $this->faker()->boolean(30)) {
                    $reporter = $this->technicianPool ? collect($this->technicianPool)->random() : ($this->userPool[0] ?? null);
                    if (! $reporter) {
                        continue;
                    }

                    $damageFactory = EquipmentDamage::factory();
                    $emCobranca = $this->faker()->boolean(50);
                    if ($emCobranca) {
                        $damageFactory = $damageFactory->emCobranca();
                    } else {
                        $damageFactory = $damageFactory->resolvido();
                    }

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

    /** @return Collection<int, Material> */
    private function seedMaterials(Tenant $tenant): Collection
    {
        $materials = collect();
        $categoryDefs = ['Filtros', 'Hidráulica', 'Elétrica', 'Consumíveis'];
        $categories = [];
        foreach ($categoryDefs as $name) {
            $categories[] = MaterialCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $name]);
        }

        $partNames = [
            'Filtro de Ar', 'Filtro de Óleo', 'Filtro de Combustível', 'Válvula Hidráulica',
            'Mangueira Hidráulica', 'Correia Dentada', 'Rolamento 6205', 'Vela de Ignição',
            'Bateria 12V 100Ah', 'Retentor de Óleo', 'Junta de Vedação', 'Pastilha de Freio',
            'Sensor de Temperatura', 'Disjuntor 32A', 'Cabo Elétrico 2.5mm',
        ];

        foreach ($partNames as $i => $name) {
            $belowMin = $this->faker()->boolean(20);
            $minStock = $this->faker()->numberBetween(5, 20);

            $materials->push(Material::firstOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => 'SKU-'.strtoupper(Str::random(6))],
                [
                    'name' => $name,
                    'material_category_id' => $categories[$i % count($categories)]->id,
                    'unit_cost' => $this->faker()->randomFloat(2, 15, 600),
                    'price' => $this->faker()->randomFloat(2, 25, 900),
                    'min_stock' => $minStock,
                    'max_stock' => $minStock * 5,
                    'current_stock' => $belowMin ? $this->faker()->numberBetween(0, max($minStock - 1, 0)) : $this->faker()->numberBetween($minStock, $minStock * 4),
                ]
            ));
        }

        return $materials;
    }

    private function seedSolicitacoesLocacao(Tenant $tenant, Collection $clients, array $assetCategories, Collection $assets): void
    {
        if (empty($this->userPool)) {
            return;
        }

        $plan = ['base' => 3, 'fechada' => 1, 'cancelada' => 1];

        foreach ($plan as $state => $count) {
            for ($i = 0; $i < $count; $i++) {
                $factory = SolicitacaoLocacao::factory();
                if ($state !== 'base') {
                    $factory = $factory->{$state}();
                }

                $factory->create([
                    'tenant_id' => $tenant->id,
                    'user_id' => collect($this->userPool)->random()->id,
                    'customer_id' => $clients->random()->id,
                    'category_id' => collect($assetCategories)->random()->id,
                    'asset_id' => $this->faker()->boolean(60) ? $assets->random()->id : null,
                ]);
            }
        }
    }

    /**
     * Fornecedores homologados, vinculados a uma parte dos materiais do
     * tenant (o resto fica sem fornecedor de proposito, reflete a
     * realidade de homologacao parcial).
     */
    private function seedSuppliers(Tenant $tenant, Collection $materials): void
    {
        $suppliers = Supplier::factory()->count(4)->create(['tenant_id' => $tenant->id]);

        if ($suppliers->isEmpty() || $materials->isEmpty()) {
            return;
        }

        foreach ($materials as $i => $material) {
            if ($i % 4 !== 3) {
                $material->update(['supplier_id' => $suppliers[$i % $suppliers->count()]->id]);
            }
        }
    }

    /**
     * Solicitacoes de pecas ligadas a OS reais, refletindo o fluxo de
     * compra (pendente -> pedida -> entregue).
     */
    private function seedPartsRequests(Tenant $tenant, Collection $maintenanceOrders, Collection $materials): void
    {
        if ($maintenanceOrders->isEmpty() || $materials->isEmpty()) {
            return;
        }

        $plan = ['pendente' => 3, 'pedida' => 2, 'entregue' => 3];

        foreach ($plan as $state => $count) {
            for ($i = 0; $i < $count; $i++) {
                $factory = PartsRequest::factory();
                if ($state !== 'pendente') {
                    $factory = $factory->{$state}();
                }

                $factory->create([
                    'tenant_id' => $tenant->id,
                    'maintenance_order_id' => $maintenanceOrders->random()->id,
                    'material_id' => $materials->random()->id,
                ]);
            }
        }
    }

    /**
     * Frota propria, transportadora terceirizada, fretes ligados a
     * mobilizacoes/desmobilizacoes reais (equipment_movements) e plano de
     * manutencao preventiva por veiculo.
     */
    private function seedFleet(Tenant $tenant, Collection $equipmentMovements): void
    {
        $vehicles = FleetVehicle::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        $vehicles->push(FleetVehicle::factory()->emManutencao()->create(['tenant_id' => $tenant->id]));

        $carrier = FreightCarrier::factory()->create(['tenant_id' => $tenant->id]);

        foreach ($vehicles as $vehicle) {
            FleetMaintenancePlan::factory()->create([
                'tenant_id' => $tenant->id,
                'fleet_vehicle_id' => $vehicle->id,
            ]);
        }

        if ($equipmentMovements->isEmpty()) {
            return;
        }

        foreach ($equipmentMovements->take(5) as $i => $movement) {
            $isProprio = $i % 2 === 0;
            $factory = $isProprio ? FreightRecord::factory() : FreightRecord::factory()->terceirizado();

            $factory->create([
                'tenant_id' => $tenant->id,
                'equipment_movement_id' => $movement->id,
                'fleet_vehicle_id' => $isProprio ? $vehicles->random()->id : null,
                'freight_carrier_id' => $isProprio ? null : $carrier->id,
            ]);
        }
    }
}
