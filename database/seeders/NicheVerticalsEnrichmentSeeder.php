<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\Department;
use App\Models\EquipmentMovement;
use App\Models\EquipmentMovementItemTemplate;
use App\Models\EquipmentMovementLocation;
use App\Models\EquipmentPatioArrival;
use App\Models\EquipmentPatioArrivalItem;
use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\FreightCarrier;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InternalUnit;
use App\Models\MaintenanceOrder;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\MaterialRequestQuotation;
use App\Models\PartsRequest;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Enriquece os 3 tenants de nicho ja criados por NicheVerticalsDemoSeeder
 * (Eventos Show Geradores / Hospital Vida Plena Energia / Construtora
 * Alicerce Locacoes) com o restante dos modulos: usuarios extras, mais
 * ativos, mais Ordens de Servico, Logistica (motoristas/veiculos/
 * rastreamento/chegada no patio), Compras (Requisicao -> Cotacao -> OC ->
 * Recebimento) e Suprimentos (materiais/fornecedor/estoque por filial).
 * Idempotente por tenant: se a Unidade Interna do tenant ja existe, pula.
 *
 * Uso: php artisan db:seed --class=NicheVerticalsEnrichmentSeeder
 */
class NicheVerticalsEnrichmentSeeder extends Seeder
{
    private ?Generator $faker = null;

    private const CONTEXTS = [
        [
            'slug' => 'eventos-show-geradores',
            'domain' => 'eventosshow.com.br',
            'client_name' => 'Festival Verão Produções',
            'unit_name' => 'Base Operacional -- Eventos',
            'unit_city' => 'São Paulo',
            'unit_state' => 'SP',
            'asset_prefix' => 'GER-EVT',
        ],
        [
            'slug' => 'hospital-vida-plena-energia',
            'domain' => 'vidaplenaenergia.com.br',
            'client_name' => 'Hospital Vida Plena -- UTI Adulto',
            'unit_name' => 'Oficina -- Vida Plena',
            'unit_city' => 'Campinas',
            'unit_state' => 'SP',
            'asset_prefix' => 'GER-UTI',
        ],
        [
            'slug' => 'construtora-alicerce-locacoes',
            'domain' => 'alicerceloca.com.br',
            'client_name' => 'Obra Residencial Jardim das Palmeiras',
            'unit_name' => 'Pátio -- Alicerce',
            'unit_city' => 'Sorocaba',
            'unit_state' => 'SP',
            'asset_prefix' => 'GER-OBRA',
        ],
    ];

    public function run(): void
    {
        foreach (self::CONTEXTS as $ctx) {
            $tenant = Tenant::where('slug', $ctx['slug'])->first();

            if (! $tenant) {
                $this->command?->warn("Tenant '{$ctx['slug']}' não existe -- rode NicheVerticalsDemoSeeder antes. Pulando.");

                continue;
            }

            if (InternalUnit::where('tenant_id', $tenant->id)->exists()) {
                $this->command?->info("Tenant '{$ctx['slug']}' já foi enriquecido -- só garantindo scheduled_at e organograma.");
                $this->ensureScheduledAt($tenant);
                $this->ensureOrganograma($tenant);

                continue;
            }

            $this->command?->info("Enriquecendo tenant '{$ctx['slug']}'...");
            $this->ensurePlanFeatures($tenant);

            DB::transaction(function () use ($tenant, $ctx) {
                $unit = $this->seedInternalUnit($tenant, $ctx);
                $client = Client::where('tenant_id', $tenant->id)->where('name', $ctx['client_name'])->firstOrFail();
                $users = $this->seedExtraUsers($tenant, $ctx);
                $assets = $this->seedExtraAssets($tenant, $ctx);
                $orders = $this->seedExtraMaintenanceOrders($tenant, $client, $assets, $users);
                $this->seedLogistica($tenant, $ctx, $assets, $orders, $users);
                $materials = $this->seedSuprimentosBase($tenant, $ctx);
                $this->seedCompras($tenant, $unit, $materials, $users);
                $this->seedPartsRequests($tenant, $orders, $materials);
            });

            $this->ensureScheduledAt($tenant);
        }

        $this->command?->info('Enriquecimento (usuários/ativos/OS/logística/compras/suprimentos) concluído.');
    }

    /**
     * Backfill idempotente de scheduled_at -- e' o campo que
     * AgendaTecnicoWidget::fetchEvents() usa pra alimentar o Calendario de
     * Manutencao. As O.S./movimentacoes semeadas por este seeder nunca
     * preenchiam esse campo, entao o calendario ficava vazio pros tenants
     * de nicho mesmo com dados reais no banco -- nao era bug de exibicao,
     * era ausencia de dado. Roda tanto na primeira semeadura quanto em
     * tenants ja enriquecidos antes (retrofit), mesmo padrao de
     * DemoGeradoresRmcSeeder::ensureScheduledVolume().
     */
    private function ensureScheduledAt(Tenant $tenant): void
    {
        $orders = MaintenanceOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('scheduled_at')
            ->whereNotIn('status', ['Concluído', 'Cancelada'])
            ->get();

        foreach ($orders as $order) {
            $order->update(['scheduled_at' => now()->addDays($this->faker()->numberBetween(1, 10))->setTime($this->faker()->numberBetween(7, 17), 0)]);
        }

        $movements = EquipmentMovement::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('scheduled_at')
            ->where('status', '!=', EquipmentMovement::STATUS_CONCLUIDO)
            ->get();

        foreach ($movements as $movement) {
            $movement->update(['scheduled_at' => now()->addDays($this->faker()->numberBetween(1, 7))->setTime($this->faker()->numberBetween(7, 17), 0)]);
        }
    }

    private function faker(): Generator
    {
        return $this->faker ??= FakerFactory::create(config('app.faker_locale', 'pt_BR'));
    }

    /**
     * Os 3 tenants de nicho compartilham o mesmo Plan ('Plano Demo Nichos',
     * criado por NicheVerticalsDemoSeeder) -- aditivo, so' acrescenta as
     * chaves que ainda faltam pros modulos novos.
     */
    private function ensurePlanFeatures(Tenant $tenant): void
    {
        $plan = $tenant->plan;
        if (! $plan) {
            return;
        }

        $needed = [
            'tabela_internal_units', 'tabela_fleet_drivers', 'tabela_fleet_vehicles',
            'tabela_freight_carriers', 'tabela_freight_records',
            'tabela_material_requests', 'tabela_purchase_orders', 'tabela_goods_receipts',
            'tabela_material_categories', 'tabela_suppliers', 'tabela_material_stock_movements',
            'tabela_material_location_stock', 'tabela_parts_requests', 'tabela_departments',
        ];

        $current = $plan->features ?? [];
        $missing = array_diff($needed, $current);
        if (! empty($missing)) {
            $plan->update(['features' => [...$current, ...array_values($missing)]]);
        }
    }

    private function seedInternalUnit(Tenant $tenant, array $ctx): InternalUnit
    {
        return InternalUnit::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $ctx['unit_name']],
            ['city' => $ctx['unit_city'], 'state' => $ctx['unit_state'], 'is_active' => true, 'type' => 'oficina']
        );
    }

    /**
     * Organograma (prova de conceito, setor Logistica): setor + niveis
     * hierarquicos -- Tecnico=1, Supervisor=5. So' isso habilita a trava
     * real de PatioAprovacoes::approve() pra estes 3 tenants
     * (Department.sector_key='logistica' + papel com hierarchy_level).
     * Idempotente e SEM criar usuario -- roda tanto na primeira
     * semeadura quanto em retrofit de tenant ja enriquecido antes.
     */
    private function ensureOrganograma(Tenant $tenant): Department
    {
        $department = Department::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'OPS'],
            ['name' => 'Operações', 'sector_key' => Department::SECTOR_LOGISTICA]
        );
        if ($department->sector_key !== Department::SECTOR_LOGISTICA) {
            $department->update(['sector_key' => Department::SECTOR_LOGISTICA]);
        }

        $tecnicoRole = Role::firstOrCreate(
            ['name' => 'tecnico', 'guard_name' => 'web', 'tenant_id' => $tenant->id],
            ['department_id' => $department->id, 'hierarchy_level' => Role::LEVEL_TECNICO]
        );
        $supervisorRole = Role::firstOrCreate(
            ['name' => 'Supervisor de Operações', 'guard_name' => 'web', 'tenant_id' => $tenant->id],
            ['department_id' => $department->id, 'hierarchy_level' => Role::LEVEL_SUPERVISOR]
        );
        if ($tecnicoRole->hierarchy_level !== Role::LEVEL_TECNICO || $tecnicoRole->department_id !== $department->id) {
            $tecnicoRole->update(['department_id' => $department->id, 'hierarchy_level' => Role::LEVEL_TECNICO]);
        }
        if ($supervisorRole->hierarchy_level !== Role::LEVEL_SUPERVISOR) {
            $supervisorRole->update(['hierarchy_level' => Role::LEVEL_SUPERVISOR]);
        }

        return $department;
    }

    /** @return array{tecnico: User, supervisor: User, suprimentos: User} */
    private function seedExtraUsers(Tenant $tenant, array $ctx): array
    {
        $department = $this->ensureOrganograma($tenant);

        $tecnicoRole = Role::where('tenant_id', $tenant->id)->where('name', 'tecnico')->firstOrFail();
        $supervisorRole = Role::where('tenant_id', $tenant->id)->where('name', 'Supervisor de Operações')->firstOrFail();
        $suprimentosRole = Role::firstOrCreate(['name' => Material::ROLE_GESTOR_SUPRIMENTOS, 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $tecnico = $this->createUser($tenant, $ctx, 'tecnico', $department->id);
        $tecnico->assignRole($tecnicoRole);

        $supervisor = $this->createUser($tenant, $ctx, 'supervisor', $department->id);
        $supervisor->assignRole($supervisorRole);

        $suprimentos = $this->createUser($tenant, $ctx, 'suprimentos', $department->id);
        $suprimentos->assignRole($suprimentosRole);

        return ['tecnico' => $tecnico, 'supervisor' => $supervisor, 'suprimentos' => $suprimentos];
    }

    private function createUser(Tenant $tenant, array $ctx, string $slugName, string $departmentId): User
    {
        $name = $this->faker()->unique()->name();
        $email = strtolower($slugName).'@'.$ctx['domain'];

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('Demo@Oravel1'),
            'tenant_id' => $tenant->id,
            'department_id' => $departmentId,
            'hourly_rate' => $this->faker()->randomFloat(2, 30, 80),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /** @return Asset[] */
    private function seedExtraAssets(Tenant $tenant, array $ctx): array
    {
        $category = AssetCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Gerador']);

        $defs = [
            ['tag' => "{$ctx['asset_prefix']}-EXTRA1", 'name' => 'Gerador Volvo Penta 250 kVA', 'status' => Asset::STATUS_DISPONIVEL, 'capacity' => 250],
            ['tag' => "{$ctx['asset_prefix']}-EXTRA2", 'name' => 'Gerador Perkins 180 kVA', 'status' => Asset::STATUS_LOCADO, 'capacity' => 180],
        ];

        $assets = [];
        foreach ($defs as $def) {
            $assets[] = Asset::firstOrCreate(
                ['tenant_id' => $tenant->id, 'tag' => $def['tag']],
                [
                    'name' => $def['name'],
                    'patrimonio' => 'PAT'.$this->faker()->unique()->numerify('######'),
                    'serial_number' => strtoupper($this->faker()->bothify('??########')),
                    'status' => $def['status'],
                    'asset_category' => $category->name,
                    'capacity_value' => $def['capacity'],
                    'capacity_unit' => 'kVA',
                    'horimetro_atual' => $this->faker()->randomFloat(2, 100, 4000),
                ]
            );
        }

        return $assets;
    }

    /** @return MaintenanceOrder[] */
    private function seedExtraMaintenanceOrders(Tenant $tenant, Client $client, array $assets, array $users): array
    {
        $states = [
            ['type' => MaintenanceOrder::TYPE_PREVENTIVE, 'status' => 'Aberto', 'internal_status' => 'aguardando_diagnostico'],
            ['type' => MaintenanceOrder::TYPE_CORRECTIVE, 'status' => 'Em Andamento', 'internal_status' => 'em_manutencao'],
            ['type' => MaintenanceOrder::TYPE_CORRECTIVE, 'status' => 'Concluído', 'internal_status' => 'concluido'],
        ];

        $orders = [];
        foreach ($states as $i => $state) {
            $asset = $assets[$i % count($assets)];
            $orders[] = MaintenanceOrder::firstOrCreate(
                ['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => "Manutenção de rotina #{$i} -- ".$asset->name],
                [
                    'technician_id' => $users['tecnico']->id,
                    'client_id' => $client->id,
                    'maintenance_type' => $state['type'],
                    'status' => $state['status'],
                    'internal_status' => $state['internal_status'],
                ]
            );
        }

        return $orders;
    }

    /**
     * Logistica: transportadora + motorista + veiculo, uma mobilizacao com
     * veiculo/motorista/km atribuidos + checkpoints de rastreamento, e uma
     * desmobilizacao concluida com "chegada no patio" (EquipmentPatioArrival)
     * completa -- mesmo padrao ja usado em NicheVerticalsDemoSeeder pro
     * cenario de Quarentena do tenant de Eventos.
     */
    private function seedLogistica(Tenant $tenant, array $ctx, array $assets, array $orders, array $users): void
    {
        $carrier = FreightCarrier::firstOrCreate(
            ['tenant_id' => $tenant->id, 'nome' => 'Transportes '.explode(' ', $ctx['unit_city'])[0]],
            ['documento' => $this->faker()->numerify('##.###.###/0001-##'), 'contato_nome' => $this->faker()->name(), 'contato_telefone' => $this->faker()->cellphoneNumber()]
        );

        $driver = FleetDriver::firstOrCreate(
            ['tenant_id' => $tenant->id, 'cpf' => $this->faker()->unique()->numerify('###.###.###-##')],
            [
                'name' => $this->faker()->name(),
                'phone' => $this->faker()->cellphoneNumber(),
                'employment_type' => FleetDriver::EMPLOYMENT_PROPRIO,
                'freight_carrier_id' => null,
                'cnh_number' => $this->faker()->numerify('###########'),
                'cnh_category' => 'D',
                'cnh_expiry_date' => now()->addYears(2),
                'active' => true,
            ]
        );

        $vehicle = FleetVehicle::firstOrCreate(
            ['tenant_id' => $tenant->id, 'placa' => strtoupper($this->faker()->bothify('???#?##'))],
            ['modelo' => 'Iveco Daily -- Munck', 'tipo' => 'caminhao', 'status' => FleetVehicle::STATUS_DISPONIVEL, 'km_atual' => $this->faker()->numberBetween(20000, 120000)]
        );
        $driver->vehicles()->syncWithoutDetaching([$vehicle->id]);

        $asset = $assets[0];
        $order = $orders[0];

        $mobilizacao = EquipmentMovement::firstOrCreate(
            ['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'type' => EquipmentMovement::TYPE_MOBILIZACAO, 'maintenance_order_id' => $order->id],
            [
                'status' => EquipmentMovement::STATUS_CONCLUIDO,
                'fleet_vehicle_id' => $vehicle->id,
                'fleet_driver_id' => $driver->id,
                'km_inicial' => $vehicle->km_atual,
                'km_final' => $vehicle->km_atual + $this->faker()->numberBetween(15, 80),
                'started_at' => now()->subHours(5),
                'completed_at' => now()->subHours(1),
            ]
        );

        $checkpoints = [
            EquipmentMovementLocation::CHECKPOINT_SAIDA_PATIO,
            EquipmentMovementLocation::CHECKPOINT_CHECKPOINT,
            EquipmentMovementLocation::CHECKPOINT_CHEGADA_DESTINO,
        ];
        foreach ($checkpoints as $i => $checkpoint) {
            EquipmentMovementLocation::firstOrCreate(
                ['tenant_id' => $tenant->id, 'equipment_movement_id' => $mobilizacao->id, 'checkpoint_type' => $checkpoint],
                [
                    'latitude' => -23.5 + $this->faker()->randomFloat(4, -0.3, 0.3),
                    'longitude' => -46.6 + $this->faker()->randomFloat(4, -0.3, 0.3),
                    'address' => $ctx['unit_city'].' - '.$ctx['unit_state'],
                    'captured_at' => now()->subHours(5)->addMinutes($i * 60),
                    'captured_by_user_id' => $users['tecnico']->id,
                ]
            );
        }

        // Chegada no patio (desmobilizacao concluida + laudo completo) --
        // mesmo padrao ja usado no cenario de Quarentena de Eventos.
        $assetRetorno = $assets[1];
        $itemTemplate = EquipmentMovementItemTemplate::where('type', EquipmentPatioArrival::TEMPLATE_TYPE)->orderBy('sort_order')->first();

        $desmobilizacao = EquipmentMovement::firstOrCreate(
            ['tenant_id' => $tenant->id, 'asset_id' => $assetRetorno->id, 'type' => EquipmentMovement::TYPE_DESMOBILIZACAO],
            ['status' => EquipmentMovement::STATUS_CONCLUIDO, 'fleet_vehicle_id' => $vehicle->id, 'fleet_driver_id' => $driver->id, 'completed_at' => now()->subDay()]
        );

        $arrival = EquipmentPatioArrival::firstOrCreate(
            ['tenant_id' => $tenant->id, 'equipment_movement_id' => $desmobilizacao->id],
            [
                'arrived_at' => now()->subDay()->addHours(2),
                'confirmed_by_user_id' => $users['supervisor']->id,
                'completed_at' => now()->subDay()->addHours(3),
                'initial_condition_notes' => 'Equipamento recebido em bom estado, sem avarias.',
            ]
        );

        if ($itemTemplate) {
            EquipmentPatioArrivalItem::firstOrCreate(
                ['tenant_id' => $tenant->id, 'equipment_patio_arrival_id' => $arrival->id, 'label' => $itemTemplate->label],
                ['sort_order' => 1, 'requires_photo' => (bool) $itemTemplate->requires_photo, 'is_checked' => true, 'has_damage' => false]
            );
        }
    }

    /** @return Material[] */
    private function seedSuprimentosBase(Tenant $tenant, array $ctx): array
    {
        $category = MaterialCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Peças de Reposição']);

        $defs = [
            ['name' => 'Filtro de Óleo', 'sku' => $ctx['asset_prefix'].'-FLT-OLEO'],
            ['name' => 'Correia do Alternador', 'sku' => $ctx['asset_prefix'].'-CORREIA'],
            ['name' => 'Bateria 12V 100Ah', 'sku' => $ctx['asset_prefix'].'-BAT12V'],
        ];

        $materials = [];
        foreach ($defs as $def) {
            $materials[] = Material::firstOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => $def['sku']],
                [
                    'name' => $def['name'],
                    'material_category_id' => $category->id,
                    'unit_cost' => $this->faker()->randomFloat(2, 40, 600),
                    'price' => $this->faker()->randomFloat(2, 80, 900),
                    'min_stock' => 3,
                    'max_stock' => 20,
                    'current_stock' => 0,
                    'requires_serial_number' => false,
                ]
            );
        }

        Supplier::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Peças & Cia -- '.$ctx['unit_city']],
            ['document' => $this->faker()->numerify('##.###.###/0001-##'), 'email' => 'contato@pecasecia.com.br', 'phone' => $this->faker()->cellphoneNumber()]
        );

        return $materials;
    }

    /**
     * Ciclo completo de Compras: Requisicao aprovada -> Cotacao selecionada
     * -> Ordem de Compra -> Recebimento. Criar o GoodsReceiptItem dispara
     * GoodsReceiptItemObserver::created(), que sozinho da entrada no
     * estoque por filial (MaterialLocationStock), grava o ledger
     * (StockMovement) e recalcula Material.current_stock e o status da OC
     * -- nao mexer nisso manualmente aqui, so' deixar o observer cascatear.
     */
    private function seedCompras(Tenant $tenant, InternalUnit $unit, array $materials, array $users): void
    {
        $supplier = Supplier::where('tenant_id', $tenant->id)->firstOrFail();
        $material = $materials[0];

        $request = MaterialRequest::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $users['tecnico']->id, 'notes' => 'Reposição de estoque -- itens de manutenção preventiva'],
            [
                'status' => MaterialRequest::STATUS_APROVADA,
                'priority' => MaterialRequest::PRIORITY_NORMAL,
                'approved_by_user_id' => $users['supervisor']->id,
                'approved_at' => now()->subDays(3),
                'requested_at' => now()->subDays(5),
            ]
        );

        if (! MaterialRequestItem::where('material_request_id', $request->id)->exists()) {
            foreach (array_slice($materials, 0, 2) as $mat) {
                MaterialRequestItem::create([
                    'material_request_id' => $request->id,
                    'material_id' => $mat->id,
                    'quantity' => $this->faker()->numberBetween(5, 15),
                    'cost_price' => $mat->unit_cost,
                ]);
            }
        }

        $quotation = MaterialRequestQuotation::firstOrCreate(
            ['tenant_id' => $tenant->id, 'material_request_id' => $request->id, 'supplier_id' => $supplier->id],
            ['total_value' => 1200.00, 'delivery_days' => 5, 'payment_terms' => '30 dias', 'is_selected' => true]
        );

        $purchaseOrder = PurchaseOrder::firstOrCreate(
            ['tenant_id' => $tenant->id, 'material_request_id' => $request->id, 'material_request_quotation_id' => $quotation->id],
            [
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrder::STATUS_ABERTA,
                'total_value' => $quotation->total_value,
                'expected_delivery_date' => now()->addDays(5),
                'created_by_user_id' => $users['suprimentos']->id,
            ]
        );

        $items = [];
        if (! PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)->exists()) {
            foreach (array_slice($materials, 0, 2) as $mat) {
                $items[] = PurchaseOrderItem::create([
                    'tenant_id' => $tenant->id,
                    'purchase_order_id' => $purchaseOrder->id,
                    'material_id' => $mat->id,
                    'quantity' => $this->faker()->numberBetween(5, 15),
                    'unit_price' => $mat->unit_cost,
                ]);
            }
        } else {
            $items = PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)->get()->all();
        }

        if (! GoodsReceipt::where('purchase_order_id', $purchaseOrder->id)->exists()) {
            $receipt = GoodsReceipt::create([
                'tenant_id' => $tenant->id,
                'purchase_order_id' => $purchaseOrder->id,
                'internal_unit_id' => $unit->id,
                'received_by_user_id' => $users['suprimentos']->id,
                'received_at' => now()->subDay(),
                'invoice_number' => 'NF-'.$this->faker()->numerify('######'),
            ]);

            foreach ($items as $item) {
                GoodsReceiptItem::create([
                    'tenant_id' => $tenant->id,
                    'goods_receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $item->id,
                    'quantity_received' => $item->quantity,
                ]);
            }
        }
    }

    private function seedPartsRequests(Tenant $tenant, array $orders, array $materials): void
    {
        PartsRequest::firstOrCreate(
            ['tenant_id' => $tenant->id, 'maintenance_order_id' => $orders[1]->id, 'material_id' => $materials[2]->id],
            ['quantity' => 2, 'status' => 'pendente', 'cost_at_time' => $materials[2]->unit_cost]
        );
    }
}
