<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\CriticalityLevel;
use App\Models\EquipmentDamage;
use App\Models\EquipmentDamageFollowUp;
use App\Models\EquipmentMovement;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use App\Models\MaintenanceOrderMaterial;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\PartsRequest;
use App\Models\Role;
use App\Models\SolicitacaoLocacao;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OrganizationalStructureSeeder;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Popula clientes, contratos, OS, materiais, fornecedores, solicitações de
 * locação etc. para tenants de prospect que JÁ EXISTEM (Alumaq,
 * Gêmeos Guindastes, Eletraq) -- diferente do RealisticDemoSeeder, que só
 * cria tenants novos e nunca roda em produção.
 *
 * Não cria tenant, não roda TenantProvisioner (admin já existe) e não cria
 * Assets (já populados via catálogo PMP em 2026-08-28) -- reaproveita os
 * ativos existentes do tenant. Departments/Roles também são reaproveitados
 * do OrganizationalStructureSeeder (8 setores reais) que o
 * TenantProvisioner já rodou na criação do tenant -- não recria uma
 * estrutura paralela simplificada.
 *
 * Idempotente por tenant: se o tenant já tem Clients, pula (não duplica).
 *
 * Uso: php artisan db:seed --class=ProspectTenantsDemoSeeder
 */
class ProspectTenantsDemoSeeder extends Seeder
{
    private const SLUGS = ['alumaq', 'gemeos-guindastes', 'eletraq'];

    private array $technicianPool = [];

    private array $userPool = [];

    public function run(): void
    {
        foreach (self::SLUGS as $slug) {
            $tenant = Tenant::where('slug', $slug)->first();

            if (! $tenant) {
                $this->command?->warn("Tenant '{$slug}' não encontrado -- pulando.");

                continue;
            }

            $this->seedTenant($tenant);
        }
    }

    private function seedTenant(Tenant $tenant): void
    {
        if (Client::where('tenant_id', $tenant->id)->exists()) {
            $this->command?->info("Tenant '{$tenant->slug}' já tem clientes -- pulando (idempotente).");

            return;
        }

        $assets = Asset::where('tenant_id', $tenant->id)->get();

        if ($assets->isEmpty()) {
            $this->command?->warn("Tenant '{$tenant->slug}' não tem Assets ainda -- pulando (rode o catálogo PMP primeiro).");

            return;
        }

        $this->command?->info("Semeando dados comerciais/operacionais para '{$tenant->name}'...");

        $this->technicianPool = [];
        $this->userPool = [];

        DB::transaction(function () use ($tenant, $assets) {
            $departments = OrganizationalStructureSeeder::seed($tenant);
            $this->seedUsers($tenant, $departments);
            $this->seedCriticalityLevels($tenant);

            $clients = Client::factory()->count(7)->create(['tenant_id' => $tenant->id]);

            // Uma parte dos ativos existentes fica "locada" com client_id, coerente com o status.
            $assets->where('status', 'locado')->each(function (Asset $asset) use ($clients) {
                $asset->update(['client_id' => $clients->random()->id]);
            });

            $this->seedContracts($tenant, $assets, $clients);
            $maintenanceOrders = $this->seedMaintenanceOrders($tenant, $assets, $clients);
            $equipmentMovements = $this->seedEquipmentMovements($tenant, $maintenanceOrders);
            $materials = $this->seedMaterials($tenant);
            $this->seedSolicitacoesLocacao($tenant, $clients, $assets);

            if ($tenant->hasFeature('tabela_suppliers')) {
                $this->seedSuppliers($tenant, $materials);
            }
            if ($tenant->hasFeature('tabela_parts_requests')) {
                $this->seedPartsRequests($tenant, $maintenanceOrders, $materials);
            }
        });

        $this->command?->info("Tenant '{$tenant->name}' concluído.");
    }

    /**
     * Departments/Roles já existem (criados por OrganizationalStructureSeeder
     * via TenantProvisioner na criação do tenant) -- aqui só busca as Roles
     * de "Gerente"/"Supervisor"/"Analista" reais por nome e cria usuários
     * fictícios vinculados a elas, sem duplicar estrutura.
     */
    private function seedUsers(Tenant $tenant, array $departments): void
    {
        $roleNames = [
            'comercial' => 'Gerente Comercial',
            'manutencao' => 'Supervisor de Manutenção',
            'logistica' => 'Analista de Logística',
            'financeiro' => 'Analista Financeiro',
            'ativos_materiais' => 'Analista de Suprimentos',
        ];

        foreach ($roleNames as $deptKey => $roleName) {
            $department = $departments[$deptKey] ?? null;
            $role = Role::where('tenant_id', $tenant->id)->where('name', $roleName)->first();

            if (! $department || ! $role) {
                continue;
            }

            $name = $this->fakeName();
            $email = strtolower(Str::slug($name, '.')).'@'.$tenant->slug.'.com.br';
            $user = $this->createUser($tenant, $name, $email, $department->id, 'colaborador');
            $user->assignRole($role);

            if ($roleName === 'Gerente Comercial') {
                $baseRole = Role::firstOrCreate(
                    ['name' => EquipmentDamage::ROLE_COMERCIAL, 'guard_name' => 'web', 'tenant_id' => $tenant->id]
                );
                $user->assignRole($baseRole);
            }

            $this->userPool[] = $user;
        }

        $manutencaoDept = $departments['manutencao'] ?? null;
        $tecnicoRole = Role::where('tenant_id', $tenant->id)->where('name', 'Técnico de Manutenção')->first();

        if ($manutencaoDept && $tecnicoRole) {
            for ($i = 0; $i < 3; $i++) {
                $name = $this->fakeName();
                $email = strtolower(Str::slug($name, '.')).'@'.$tenant->slug.'.com.br';
                $user = $this->createUser($tenant, $name, $email, $manutencaoDept->id, 'tecnico');
                $user->assignRole($tecnicoRole);
                $this->technicianPool[] = $user;
                $this->userPool[] = $user;
            }
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

    /** @return Collection<int, MaintenanceOrder> */
    private function seedMaintenanceOrders(Tenant $tenant, Collection $assets, Collection $clients)
    {
        $ranges = [
            'aguardandoDiagnostico' => [1, 4],
            'emManutencao' => [2, 5],
            'aguardandoPeca' => [1, 4],
            'testeQualidade' => [1, 3],
            'pendencia' => [1, 3],
            'concluido' => [2, 6],
            'cancelada' => [0, 1],
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
    private function seedEquipmentMovements(Tenant $tenant, Collection $maintenanceOrders): Collection
    {
        $movements = collect();

        if ($maintenanceOrders->isEmpty()) {
            return $movements;
        }

        $states = [
            'aguardandoVistoria' => $this->faker()->numberBetween(1, 4),
            'emAndamento' => $this->faker()->numberBetween(1, 3),
            'concluido' => $this->faker()->numberBetween(1, 3),
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

                if ($stateKey !== 'aguardandoVistoria' && $this->faker()->boolean(30)) {
                    $reporter = $this->technicianPool ? collect($this->technicianPool)->random() : ($this->userPool[0] ?? null);
                    if (! $reporter) {
                        continue;
                    }

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
            'Bateria 12V 100Ah', 'Retentor de Óleo', 'Junta de Vedação', 'Cabo de Aço',
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

    private function seedSolicitacoesLocacao(Tenant $tenant, Collection $clients, Collection $assets): void
    {
        if (empty($this->userPool)) {
            return;
        }

        $categoryIds = $assets->pluck('asset_category_id')->filter()->unique()->values();

        if ($categoryIds->isEmpty()) {
            return;
        }

        $plan = ['base' => 3, 'fechada' => 1, 'cancelada' => 1];

        // Consulta o status atual no banco (não a collection $assets em
        // memória, que pode estar stale -- ContractObserver::created() muda
        // o status do Asset pra "locado" quando um Contract é criado em
        // seedContracts(), rodado antes desta função).
        $disponiveis = Asset::whereIn('id', $assets->pluck('id'))
            ->where('status', Asset::STATUS_DISPONIVEL)
            ->get();

        foreach ($plan as $state => $count) {
            for ($i = 0; $i < $count; $i++) {
                $factory = SolicitacaoLocacao::factory();
                if ($state !== 'base') {
                    $factory = $factory->{$state}();
                }

                // "fechada" exige asset disponível no pátio (regra do model
                // SolicitacaoLocacao::booted()) -- só vincula asset_id se
                // houver um disponível, senão deixa null.
                $assetId = null;
                if ($state === 'fechada') {
                    $assetId = $disponiveis->isNotEmpty() ? $disponiveis->random()->id : null;
                } elseif ($this->faker()->boolean(60)) {
                    $assetId = $assets->random()->id;
                }

                $factory->create([
                    'tenant_id' => $tenant->id,
                    'user_id' => collect($this->userPool)->random()->id,
                    'customer_id' => $clients->random()->id,
                    'category_id' => $categoryIds->random(),
                    'asset_id' => $assetId,
                ]);
            }
        }
    }

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

    private function seedPartsRequests(Tenant $tenant, Collection $maintenanceOrders, Collection $materials): void
    {
        if ($maintenanceOrders->isEmpty() || $materials->isEmpty()) {
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
                    'maintenance_order_id' => $maintenanceOrders->random()->id,
                    'material_id' => $materials->random()->id,
                ]);
            }
        }
    }
}
