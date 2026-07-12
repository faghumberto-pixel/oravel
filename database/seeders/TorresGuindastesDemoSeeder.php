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
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * Cenario de demonstracao comercial (guindaste em manutencao + proposta de
 * locacao + checklist de despacho com aprovacao do patio) -- usado nas
 * primeiras visitas de venda. Idempotente: pode rodar de novo sem duplicar
 * (usa firstOrCreate pelas chaves naturais). Senha padrao de todos os
 * usuarios de demo: "Demo@Oravel1".
 *
 * O bloco de enriquecimento em volume (seedEnrichment) e escopado 100% ao
 * tenant torres-guindastes -- nunca itera Tenant::all() como
 * BasicChecklistTemplateSeeder/PreventiveMaintenanceTemplateSeeder/
 * EquipmentReplacementDemoSeeder fazem, propositalmente: aqueles rodam
 * global e poluiriam tenants de clientes reais em producao. Os dados
 * daqueles arquivos (grupos de checklist, planos preventivos) foram
 * embutidos aqui, escopados so a esse tenant.
 *
 * checklist_templates (tabela `checklist_templates` / model
 * ChecklistTemplate) fica de fora de proposito: e scaffolding morta, sem
 * nenhum seeder/controller/fluxo real que leia ou escreva nela -- nao
 * confundir com ChecklistGroup + MaintenanceOrderChecklist(is_template),
 * que sao o mecanismo real usado pelas OS.
 *
 * Uso: php artisan db:seed --class=TorresGuindastesDemoSeeder
 */
class TorresGuindastesDemoSeeder extends Seeder
{
    private ?Generator $faker = null;

    /** @var array<int, User> */
    private array $technicianPool = [];

    /** @var array<int, User> */
    private array $userPool = [];

    /**
     * Itens de checklist-template por grupo de equipamento, copiados de
     * BasicChecklistTemplateSeeder (so os 5 grupos relevantes pra uma
     * locadora de guindastes).
     */
    private const CHECKLIST_ITEMS_BY_GROUP = [
        'Geradores de Energia' => [
            'objetivo' => 'Evitar falhas de partida e superaquecimento.',
            'itens' => [
                ['item_name' => 'Nível de Óleo do Motor', 'instructions' => 'Dentro da faixa'],
                ['item_name' => 'Nível do Fluido de Arrefecimento', 'instructions' => 'Radiador'],
                ['item_name' => 'Combustível', 'instructions' => 'Nível no tanque'],
                ['item_name' => 'Bateria', 'instructions' => 'Tensão, oxidação nos bornes'],
                ['item_name' => 'Vazamentos', 'instructions' => 'Óleo, combustível ou água'],
                ['item_name' => 'Filtros', 'instructions' => 'Verificar obstrução visível'],
                ['item_name' => 'Painel de Controle', 'instructions' => 'Sem alarmes/mensagens'],
                ['item_name' => 'Escapamento', 'instructions' => 'Fixação e ausência de corrosão'],
            ],
        ],
        'Compressores de Ar' => [
            'objetivo' => 'Integridade do vaso de pressão (NR-13) e vedação.',
            'itens' => [
                ['item_name' => 'Válvula de Segurança', 'instructions' => 'Teste manual - alívio'],
                ['item_name' => 'Dreno do Reservatório', 'instructions' => 'Realizada purga'],
                ['item_name' => 'Manômetro de Pressão', 'instructions' => 'Calibrado/visível'],
                ['item_name' => 'Correias', 'instructions' => 'Tensão e estado de conservação'],
                ['item_name' => 'Vazamentos de Ar', 'instructions' => 'Mangueiras/conexões'],
                ['item_name' => 'Nível de Óleo do Cabeçote', 'instructions' => null],
                ['item_name' => 'Chave de Emergência', 'instructions' => 'Acionamento/reset'],
            ],
        ],
        'Caminhão Munck' => [
            'objetivo' => 'Estabilidade e movimentação de carga.',
            'itens' => [
                ['item_name' => 'Patolas', 'instructions' => 'Cilindros, calços e nivelamento'],
                ['item_name' => 'Cabo de Aço', 'instructions' => 'Fios rompidos, nós ou oxidação'],
                ['item_name' => 'Gancho', 'instructions' => 'Trava de segurança íntegra'],
                ['item_name' => 'Lança Telescópica', 'instructions' => 'Trincas, soldas, empenos'],
                ['item_name' => 'Controles/Alavancas', 'instructions' => 'Funcionalidade'],
                ['item_name' => 'Sinalização Sonora/Visual', 'instructions' => 'De ré/giro'],
                ['item_name' => 'Tabela de Carga', 'instructions' => 'Legível e presente'],
            ],
        ],
        'Plataformas Elevatórias' => [
            'objetivo' => 'Operação em altura com segurança total.',
            'itens' => [
                ['item_name' => 'Joystick de Controle', 'instructions' => 'Resposta imediata'],
                ['item_name' => 'Dispositivo de Descida de Emergência', 'instructions' => null],
                ['item_name' => 'Sensor de Inclinação', 'instructions' => 'Teste de alarme'],
                ['item_name' => 'Pneus/Rodas', 'instructions' => 'Integridade/corte/desgaste'],
                ['item_name' => 'Guarda-Corpo da Cesta', 'instructions' => 'Travamento'],
                ['item_name' => 'Bateria/Carga', 'instructions' => 'Nível da carga'],
                ['item_name' => 'Buzina/Alarme de Movimentação', 'instructions' => null],
            ],
        ],
        'Guindastes' => [
            'objetivo' => 'Operação crítica de grande porte.',
            'itens' => [
                ['item_name' => 'LMI', 'instructions' => 'Indicador de Momento de Carga - ON'],
                ['item_name' => 'Freios de Giro e Elevação', 'instructions' => 'Teste funcional'],
                ['item_name' => 'Cabo e Moitão', 'instructions' => 'Estado do cabo/rolamento'],
                ['item_name' => 'Contra-peso', 'instructions' => 'Fixação e travamento'],
                ['item_name' => 'Nivelamento do Guindaste', 'instructions' => 'Bolha de nível'],
                ['item_name' => 'Mangueiras Hidráulicas', 'instructions' => 'Vazamentos/bolhas'],
            ],
        ],
    ];

    /** Templates de manutencao preventiva por grupo, copiados de PreventiveMaintenanceTemplateSeeder. */
    private const PREVENTIVE_ITEMS_BY_GROUP = [
        'Geradores de Energia' => [
            ['name' => 'Troca de óleo do motor', 'interval_hours' => 250],
            ['name' => 'Troca de filtro de óleo', 'interval_hours' => 250],
            ['name' => 'Troca de filtro de ar', 'interval_hours' => 500],
            ['name' => 'Troca de filtro de combustível', 'interval_hours' => 500],
            ['name' => 'Verificação/troca de correias', 'interval_hours' => 1000],
            ['name' => 'Análise de fluido de arrefecimento', 'interval_hours' => 1000],
            ['name' => 'Teste de bateria', 'interval_hours' => 500],
        ],
        'Compressores de Ar' => [
            ['name' => 'Troca de óleo do compressor', 'interval_hours' => 500],
            ['name' => 'Troca de filtro separador de óleo', 'interval_hours' => 1000],
            ['name' => 'Troca de filtro de admissão', 'interval_hours' => 500],
            ['name' => 'Teste de válvula de segurança', 'interval_hours' => 1000, 'notes' => 'Obrigatório NR-13'],
            ['name' => 'Verificação de correias', 'interval_hours' => 500],
        ],
        'Plataformas Elevatórias' => [
            ['name' => 'Lubrificação geral', 'interval_hours' => 250],
            ['name' => 'Verificação de nível de óleo hidráulico', 'interval_hours' => 250],
            ['name' => 'Troca de óleo hidráulico', 'interval_hours' => 1000],
            ['name' => 'Inspeção de cabos/correntes', 'interval_hours' => 500],
            ['name' => 'Teste de sistema de emergência', 'interval_hours' => 500],
        ],
        'Caminhão Munck' => [
            ['name' => 'Troca de óleo hidráulico', 'interval_hours' => 500],
            ['name' => 'Lubrificação de lança e cabos', 'interval_hours' => 250],
            ['name' => 'Inspeção de cabo de aço', 'interval_hours' => 250, 'notes' => 'Crítico de segurança'],
            ['name' => 'Revisão de freios de giro', 'interval_hours' => 1000],
        ],
        'Guindastes' => [
            ['name' => 'Troca de óleo hidráulico', 'interval_hours' => 500],
            ['name' => 'Lubrificação de lança e cabos', 'interval_hours' => 250],
            ['name' => 'Inspeção de cabo de aço', 'interval_hours' => 250, 'notes' => 'Crítico de segurança'],
            ['name' => 'Revisão de freios de giro', 'interval_hours' => 1000],
        ],
    ];

    /** Ativos "de vitrine" por grupo, nome/status/horimetro plausiveis pra uma locadora de guindastes. */
    private const ASSET_DEFS = [
        'Guindastes' => [
            ['name' => 'Guindaste Liebherr LTM 1090', 'capacity_value' => 90, 'capacity_unit' => 'ton', 'status' => Asset::STATUS_LOCADO, 'veterano' => false],
            ['name' => 'Guindaste Grove GMK 4100', 'capacity_value' => 100, 'capacity_unit' => 'ton', 'status' => Asset::STATUS_MANUTENCAO, 'veterano' => true],
            ['name' => 'Guindaste Tadano ATF 60G', 'capacity_value' => 60, 'capacity_unit' => 'ton', 'status' => Asset::STATUS_DISPONIVEL, 'veterano' => false],
            ['name' => 'Guindaste Liebherr LTM 1250', 'capacity_value' => 250, 'capacity_unit' => 'ton', 'status' => Asset::STATUS_LOCADO, 'veterano' => true],
            ['name' => 'Guindaste XCMG QY25', 'capacity_value' => 25, 'capacity_unit' => 'ton', 'status' => Asset::STATUS_DISPONIVEL, 'veterano' => false],
        ],
        'Caminhão Munck' => [
            ['name' => 'Caminhão Munck Volvo VM 8x2', 'capacity_value' => 15, 'capacity_unit' => 'ton', 'status' => Asset::STATUS_LOCADO, 'veterano' => false],
            ['name' => 'Caminhão Munck Mercedes Atego', 'capacity_value' => 8, 'capacity_unit' => 'ton', 'status' => Asset::STATUS_DISPONIVEL, 'veterano' => true],
            ['name' => 'Caminhão Munck Ford Cargo', 'capacity_value' => 10, 'capacity_unit' => 'ton', 'status' => Asset::STATUS_MANUTENCAO, 'veterano' => false],
            ['name' => 'Caminhão Munck Volkswagen Constellation', 'capacity_value' => 12, 'capacity_unit' => 'ton', 'status' => Asset::STATUS_DISPONIVEL, 'veterano' => false],
        ],
        'Plataformas Elevatórias' => [
            ['name' => 'Plataforma Tesoura JLG 3369E', 'capacity_value' => 12, 'capacity_unit' => 'm', 'status' => Asset::STATUS_DISPONIVEL, 'veterano' => false],
            ['name' => 'Plataforma Articulada Genie Z-60', 'capacity_value' => 18, 'capacity_unit' => 'm', 'status' => Asset::STATUS_LOCADO, 'veterano' => true],
            ['name' => 'Plataforma Tesoura Haulotte Compact 12', 'capacity_value' => 12, 'capacity_unit' => 'm', 'status' => Asset::STATUS_DISPONIVEL, 'veterano' => false],
        ],
        'Compressores de Ar' => [
            ['name' => 'Compressor de Ar Atlas Copco XATS 400', 'capacity_value' => 400, 'capacity_unit' => 'pcm', 'status' => Asset::STATUS_DISPONIVEL, 'veterano' => false],
            ['name' => 'Compressor de Ar Chicago Pneumatic CPS 185', 'capacity_value' => 185, 'capacity_unit' => 'pcm', 'status' => Asset::STATUS_LOCADO, 'veterano' => true],
        ],
    ];

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

        $this->userPool = [$admin, $comercial];
        $this->technicianPool = [$tecnico];

        $this->seedEnrichment($tenant, $tecnicoRole, $client);
    }

    /**
     * Bloco de volume/variedade realista, escopado 100% ao Torres. Guarda
     * de idempotencia: se o tenant ja tem 5+ ativos, o enriquecimento ja
     * rodou antes -- nao ha idempotencia fina registro-a-registro pros
     * dados gerados via factory/random, so essa guarda de bloco (mesmo
     * padrao ja usado por RealisticDemoSeeder/EquipmentReplacementDemoSeeder).
     */
    private function seedEnrichment(Tenant $tenant, Role $tecnicoRole, Client $baseClient): void
    {
        if (Asset::where('tenant_id', $tenant->id)->count() >= 5) {
            $this->command?->info('Torres & Guindastes: enriquecimento já rodou antes -- pulando.');

            return;
        }

        $this->command?->info('Torres & Guindastes: iniciando enriquecimento com dados de volume...');

        DB::transaction(function () use ($tenant, $tecnicoRole, $baseClient) {
            $department = $this->seedDepartments($tenant);
            $this->seedExtraUsers($tenant, $department, $tecnicoRole);
            $this->seedCriticalityLevels($tenant);
            $groups = $this->seedChecklistGroupsAndTemplates($tenant);
            $this->seedPreventiveTemplates($tenant, $groups);
            $this->seedAssetCategories($tenant, $groups);

            $clients = $this->seedClients($tenant, $baseClient);
            $assets = $this->seedAssets($tenant, $groups);
            $this->seedContracts($tenant, $assets, $clients);
            $orders = $this->seedMaintenanceOrders($tenant, $assets, $clients);
            $this->seedEquipmentMovements($tenant, $orders);
            $this->seedEquipmentReplacements($tenant, $orders);
            $materials = $this->seedMaterials($tenant);
            $this->seedSuppliers($tenant, $materials);
            $this->seedPartsRequests($tenant, $orders, $materials);
            $this->seedSolicitacoesLocacao($tenant, $clients, $assets);
            $this->seedAbcMatrix($tenant, $assets);
            $this->seedPreventiveMaintenanceExecutions($tenant, $assets, $orders);
            $this->seedUserActivityLogs($tenant);
        });

        $this->command?->info('Torres & Guindastes: enriquecimento concluído.');
    }

    private function seedDepartments(Tenant $tenant): array
    {
        $defs = [
            'comercial' => ['name' => 'Comercial', 'code' => 'COM'],
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

    private function seedExtraUsers(Tenant $tenant, array $departments, Role $tecnicoRole): void
    {
        $supervisorRole = Role::firstOrCreate(
            ['name' => 'Supervisor de Manutenção', 'guard_name' => 'web', 'tenant_id' => $tenant->id],
            ['department_id' => $departments['manutencao']->id]
        );

        $supervisorName = $this->fakeName();
        $supervisor = $this->createUser(
            $tenant,
            $supervisorName,
            strtolower(Str::slug($supervisorName, '.')).'@torresguindastes.com.br',
            $departments['manutencao']->id
        );
        $supervisor->assignRole($supervisorRole);
        $this->userPool[] = $supervisor;

        for ($i = 0; $i < 2; $i++) {
            $name = $this->fakeName();
            $user = $this->createUser(
                $tenant,
                $name,
                strtolower(Str::slug($name, '.')).'@torresguindastes.com.br',
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

    private function faker(): Generator
    {
        return $this->faker ??= FakerFactory::create(config('app.faker_locale', 'pt_BR'));
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

    /** @return array<string, ChecklistGroup> */
    private function seedChecklistGroupsAndTemplates(Tenant $tenant): array
    {
        $groups = [];

        foreach (self::CHECKLIST_ITEMS_BY_GROUP as $groupName => $def) {
            $group = ChecklistGroup::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $groupName],
                ['description' => $def['objetivo']]
            );
            $groups[$groupName] = $group;

            foreach ($def['itens'] as $item) {
                MaintenanceOrderChecklist::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'checklist_group_id' => $group->id,
                        'item_name' => $item['item_name'],
                        'is_template' => true,
                    ],
                    [
                        'category' => $groupName,
                        'instructions' => $item['instructions'],
                        'section' => $groupName,
                        'checklist_type' => 'Preventiva',
                        'is_completed' => false,
                    ]
                );
            }
        }

        return $groups;
    }

    private function seedPreventiveTemplates(Tenant $tenant, array $groups): void
    {
        foreach (self::PREVENTIVE_ITEMS_BY_GROUP as $groupName => $items) {
            $group = $groups[$groupName] ?? null;
            if (! $group) {
                continue;
            }

            foreach ($items as $item) {
                MaintenancePlan::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'checklist_group_id' => $group->id,
                        'name' => $item['name'],
                    ],
                    [
                        'interval_hours' => $item['interval_hours'],
                        'notes' => $item['notes'] ?? null,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function seedAssetCategories(Tenant $tenant, array $groups): void
    {
        foreach (array_keys($groups) as $groupName) {
            AssetCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $groupName]);
        }
    }

    /** @return Collection<int, Client> */
    private function seedClients(Tenant $tenant, Client $baseClient): Collection
    {
        $names = [
            'Rocha Engenharia e Montagens Industriais',
            'Sudeste Construções e Empreendimentos',
            'Terraplan Terraplenagem e Obras',
            'Metálica Estruturas e Fundações',
            'Vetor Engenharia Pesada',
        ];

        foreach ($names as $name) {
            Client::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $name]);
        }

        return Client::where('tenant_id', $tenant->id)->get()->push($baseClient)->unique('id')->values();
    }

    /** @return Collection<int, Asset> */
    private function seedAssets(Tenant $tenant, array $groups): Collection
    {
        $assets = collect();

        foreach (self::ASSET_DEFS as $groupName => $defs) {
            $group = $groups[$groupName] ?? null;

            foreach ($defs as $i => $def) {
                $tag = strtoupper(Str::substr($groupName, 0, 3)).'-'.($i + 1);

                $asset = Asset::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'tag' => $tag],
                    [
                        'name' => $def['name'],
                        'patrimonio' => 'PAT'.$this->faker()->unique()->numerify('######'),
                        'serial_number' => strtoupper($this->faker()->bothify('??########')),
                        'status' => $def['status'],
                        'criticality' => $def['veterano'] ? 'high' : 'medium',
                        'criticality_level' => $def['veterano'] ? 'Alta' : 'Média',
                        'asset_category' => $groupName,
                        'checklist_group_id' => $group?->id,
                        'capacity_value' => $def['capacity_value'],
                        'capacity_unit' => $def['capacity_unit'],
                        'acquisition_value' => $this->faker()->randomFloat(2, 80000, 900000),
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
    private function seedMaintenanceOrders(Tenant $tenant, Collection $assets, Collection $clients): Collection
    {
        $ranges = [
            'aguardandoDiagnostico' => [2, 4],
            'emManutencao' => [2, 4],
            'aguardandoPeca' => [1, 3],
            'testeQualidade' => [1, 3],
            'pendencia' => [1, 2],
            'concluido' => [3, 5],
            'cancelada' => [0, 2],
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
    private function seedEquipmentMovements(Tenant $tenant, Collection $orders): Collection
    {
        $movements = collect();

        if ($orders->isEmpty()) {
            return $movements;
        }

        $states = [
            'aguardandoVistoria' => $this->faker()->numberBetween(2, 4),
            'emAndamento' => $this->faker()->numberBetween(1, 3),
            'concluido' => $this->faker()->numberBetween(1, 3),
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
     * Cenarios de troca de equipamento, adaptados de EquipmentReplacementDemoSeeder
     * mas escopados so ao Torres (o original itera Tenant::all(), nao pode
     * rodar direto em producao).
     */
    private function seedEquipmentReplacements(Tenant $tenant, Collection $orders): void
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

        if ($commercialUser && $sharedAsset) {
            $replacement = $this->createReplacementRequest($tenant, $ordersWithTech->random());
            $replacement->identifyReplacement($sharedAsset, $commercialUser);
            $replacement->startLogisticsMovements();
            $replacement->desmobilizationMovement->update([
                'status' => EquipmentMovement::STATUS_CONCLUIDO,
                'completed_at' => now()->subDays(2),
            ]);
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
    private function seedMaterials(Tenant $tenant): Collection
    {
        $materials = collect();
        $categoryDefs = ['Cabos e Rigging', 'Hidráulica', 'Elétrica', 'Consumíveis'];
        $categories = [];
        foreach ($categoryDefs as $name) {
            $categories[] = MaterialCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $name]);
        }

        $partNames = [
            'Cabo de Aço 16mm', 'Moitão de Carga', 'Manilha 10T', 'Válvula Hidráulica',
            'Mangueira Hidráulica', 'Correia Dentada', 'Rolamento 6205', 'Bateria 12V 150Ah',
            'Retentor de Óleo', 'Junta de Vedação', 'Sensor de Carga LMI', 'Filtro Hidráulico',
        ];

        foreach ($partNames as $i => $name) {
            $belowMin = $this->faker()->boolean(20);
            $minStock = $this->faker()->numberBetween(3, 15);

            $materials->push(Material::firstOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => 'TG-'.strtoupper(Str::random(6))],
                [
                    'name' => $name,
                    'material_category_id' => $categories[$i % count($categories)]->id,
                    'unit_cost' => $this->faker()->randomFloat(2, 30, 1500),
                    'price' => $this->faker()->randomFloat(2, 50, 2200),
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

    private function seedPartsRequests(Tenant $tenant, Collection $orders, Collection $materials): void
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
                // (SolicitacaoLocacao::booted() valida isso no saving()) -- e
                // precisa ser lido do banco na hora, nao da colecao $assets
                // capturada antes de seedContracts/seedEquipmentReplacements
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
