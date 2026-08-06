<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\ChecklistGroup;
use App\Models\Client;
use App\Models\Contract;
use App\Models\EquipmentDamage;
use App\Models\EquipmentMovement;
use App\Models\EquipmentMovementItemTemplate;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SolicitacaoLocacao;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Popula DEV com 8 tenants especificos pra demonstracao comercial ao vivo,
 * cada um com um "gatilho comercial" pronto pra ser encenado na tela real
 * do sistema. Dados especificos (nomes, numeros de serie, status) definidos
 * junto com o time comercial -- NAO sao dados aleatorios, nao regenerar com
 * valores diferentes sem confirmar de novo.
 *
 * NUNCA rodar em producao -- guard abaixo aborta se o ambiente for 'production'.
 * Idempotente por tenant: se o slug ja existe, pula aquele tenant inteiro.
 *
 * Uso: php artisan db:seed --class=DemoTenantsSeeder
 *
 * Pre-requisito: reaproveita os grupos/itens ja seedados por
 * BasicChecklistTemplateSeeder (Geradores de Energia, Compressores de Ar,
 * Caminhão Munck, Plataformas Elevatórias, Guindastes) e
 * PreventiveMaintenanceTemplateSeeder (25 itens de preventiva por grupo) --
 * ambos sao chamados no final deste seeder pra garantir que os novos tenants
 * tambem tenham esses templates (sao idempotentes, nao duplicam nada).
 */
class DemoTenantsSeeder extends Seeder
{
    public function run(): void
    {
        // Guard original (abort incondicional) trocado por confirmação
        // explícita em 2026-08-06 -- ver mesma decisão documentada em
        // DemoVerticalGapsSeeder::run().
        if (app()->environment('production') && env('ALLOW_DEMO_SEED_IN_PRODUCTION') !== 'true') {
            abort(1, 'DemoTenantsSeeder requer ALLOW_DEMO_SEED_IN_PRODUCTION=true para rodar em produção -- dado fictício de demonstração comercial, ver decisão de 2026-08-06.');
        }

        // Tabela global (nao tenant-scoped) usada pelo EquipmentMovementObserver
        // pra gerar os itens de checklist de mobilizacao/desmobilizacao --
        // estava vazia em DEV (nenhum seeder a populava), entao nenhuma
        // movimentacao (nova ou antiga) tinha item nenhum de checklist pra
        // marcar. Idempotente (firstOrCreate), beneficia todos os tenants.
        $this->seedEquipmentMovementItemTemplates();

        $plan = $this->createDemoPlan();

        $this->seedMunkmaq($plan);
        $this->seedTecnogen($plan);
        $this->seedCunzolo($plan);
        $this->seedSuperinfra($plan);
        $this->seedRmcPlataformas($plan);
        $this->seedGeradoresCampinas($plan);
        $this->seedLomaq($plan);
        $this->seedLmaLocacoes($plan);

        $this->command?->info('DemoTenantsSeeder concluído -- 8 tenants de demonstração prontos.');
    }

    private function seedEquipmentMovementItemTemplates(): void
    {
        $desmobilizacao = [
            ['section' => 'Geral', 'label' => 'Limpeza geral do equipamento', 'sort_order' => 1, 'requires_photo' => false],
            ['section' => 'Geral', 'label' => 'Inspeção visual de vazamentos', 'sort_order' => 2, 'requires_photo' => true],
            ['section' => 'Mecânica', 'label' => 'Verificação de níveis de fluido (óleo/hidráulico)', 'sort_order' => 3, 'requires_photo' => false],
            ['section' => 'Operacional', 'label' => 'Teste de funcionamento dos comandos', 'sort_order' => 4, 'requires_photo' => false],
            ['section' => 'Operacional', 'label' => 'Registro do horímetro/odômetro final', 'sort_order' => 5, 'requires_photo' => false],
            ['section' => 'Segurança', 'label' => 'Verificação de avarias/danos', 'sort_order' => 6, 'requires_photo' => true],
            ['section' => 'Geral', 'label' => 'Conferência de acessórios/ferramentas', 'sort_order' => 7, 'requires_photo' => false],
        ];

        $mobilizacao = [
            ['section' => 'Mecânica', 'label' => 'Verificação de níveis de fluido antes da saída', 'sort_order' => 1, 'requires_photo' => false],
            ['section' => 'Operacional', 'label' => 'Teste de partida e funcionamento', 'sort_order' => 2, 'requires_photo' => false],
            ['section' => 'Segurança', 'label' => 'Inspeção de pneus/esteiras/cabos', 'sort_order' => 3, 'requires_photo' => false],
            ['section' => 'Geral', 'label' => 'Fotos do estado de saída', 'sort_order' => 4, 'requires_photo' => true],
            ['section' => 'Operacional', 'label' => 'Registro do horímetro/odômetro inicial', 'sort_order' => 5, 'requires_photo' => false],
            ['section' => 'Segurança', 'label' => 'Verificação de itens de segurança (extintor, sinalização)', 'sort_order' => 6, 'requires_photo' => false],
        ];

        foreach (['desmobilizacao' => $desmobilizacao, 'mobilizacao' => $mobilizacao] as $type => $items) {
            foreach ($items as $item) {
                EquipmentMovementItemTemplate::firstOrCreate(
                    ['type' => $type, 'label' => $item['label']],
                    ['section' => $item['section'], 'sort_order' => $item['sort_order'], 'requires_photo' => $item['requires_photo']]
                );
            }
        }
    }

    private function createDemoPlan(): Plan
    {
        return Plan::firstOrCreate(
            ['name' => 'Plano Demo Comercial'],
            [
                'price' => 1999, 'base_price' => 1999, 'level' => 3, 'billing_cycle' => 'monthly',
                'is_active' => true,
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
    }

    /**
     * Provisiona o tenant + os 3 usuarios minimos pra operar a demo
     * (admin via TenantProvisioner, 1 tecnico, 1 comercial).
     *
     * @return array{0: Tenant, 1: User, 2: User}
     */
    private function setupTenant(string $name, string $slug, Plan $plan): array
    {
        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'plan_id' => $plan->id,
            'onboarding_completed' => true,
        ]);

        // Precisa rodar AQUI, antes de qualquer Asset/MaintenanceOrder deste
        // tenant ser criado -- Grupos de Checklist e itens de preventiva sao
        // referenciados logo em seguida por nome (ex: "Geradores de Energia").
        // Idempotente (firstOrCreate), rodar de novo pra tenants ja prontos
        // nao duplica nada.
        (new BasicChecklistTemplateSeeder)->run();
        (new PreventiveMaintenanceTemplateSeeder)->run();

        $admin = TenantProvisioner::provision($tenant, [
            'name' => 'Admin '.$name,
            'email' => 'admin@'.$slug.'.com.br',
            'password' => 'demo12345',
        ]);

        $tecnicoRole = Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $tecnico = $this->createUser($tenant, 'Técnico '.$name, 'tecnico@'.$slug.'.com.br', 'tecnico');
        $tecnico->assignRole($tecnicoRole);

        $comercialRole = Role::firstOrCreate(['name' => EquipmentDamage::ROLE_COMERCIAL, 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $comercial = $this->createUser($tenant, 'Comercial '.$name, 'comercial@'.$slug.'.com.br', 'comercial');
        $comercial->assignRole($comercialRole);

        return [$tenant, $tecnico, $comercial];
    }

    private function createUser(Tenant $tenant, string $name, string $email, string $role): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('demo12345'),
            'tenant_id' => $tenant->id,
            'role' => $role,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function checklistGroup(Tenant $tenant, string $name): ?ChecklistGroup
    {
        return ChecklistGroup::where('tenant_id', $tenant->id)->where('name', $name)->first();
    }

    /**
     * Snapshot do checklist basico do Grupo numa OS real, todos marcados
     * como OK ("conforme") -- pra demonstrar o "checklist de seguranca
     * integrado" ja registrado no sistema.
     */
    private function markGroupChecklistOk(Tenant $tenant, MaintenanceOrder $order, Asset $asset, ChecklistGroup $group): void
    {
        foreach ($group->templateItems as $templateItem) {
            MaintenanceOrderChecklist::create([
                'tenant_id' => $tenant->id,
                'maintenance_order_id' => $order->id,
                'asset_id' => $asset->id,
                'checklist_group_id' => $group->id,
                'category' => $templateItem->category,
                'item_name' => $templateItem->item_name,
                'status' => 'conforme',
                'is_template' => false,
            ]);
        }
    }

    private function alreadySeeded(string $slug): bool
    {
        if (Tenant::where('slug', $slug)->exists()) {
            $this->command?->info("Tenant '{$slug}' já existe -- pulando (idempotente).");

            return true;
        }

        return false;
    }

    /**
     * 1. MUNKMAQ -- Munck em manutenção com locação urgente pendente
     * pro mesmo ativo (destaque visual no Kanban).
     */
    private function seedMunkmaq(Plan $plan): void
    {
        if ($this->alreadySeeded('munkmaq')) {
            return;
        }

        DB::transaction(function () use ($plan) {
            [$tenant, $tecnico, $comercial] = $this->setupTenant('Guindaste Demo', 'munkmaq', $plan);

            $group = $this->checklistGroup($tenant, 'Caminhão Munck');
            $klabin = Client::create(['tenant_id' => $tenant->id, 'name' => 'Papel e Celulose Vale Sul']);

            $asset = Asset::create([
                'tenant_id' => $tenant->id,
                'name' => 'Munck 45T',
                'tag' => 'MNK-4526',
                'patrimonio' => 'PAT-MNK-4526',
                'serial_number' => 'MNK-4526',
                'status' => Asset::STATUS_MANUTENCAO,
                'asset_category' => 'Caminhão Munck Pesado',
                'checklist_group_id' => $group?->id,
                'description' => 'Aguardando teste de torque da lança',
            ]);

            MaintenanceOrder::create([
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'technician_id' => $tecnico->id,
                'description' => 'Aguardando teste de torque da lança',
                'technical_notes' => 'Aguardando teste de torque da lança',
                'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
                'status' => 'Em Andamento',
                'internal_status' => 'em_manutencao',
            ]);

            $category = AssetCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Caminhão Munck Pesado']);

            SolicitacaoLocacao::create([
                'tenant_id' => $tenant->id,
                'user_id' => $comercial->id,
                'customer_id' => $klabin->id,
                'category_id' => $category->id,
                'asset_id' => $asset->id,
                'purpose' => 'Montagem industrial em Hortolândia',
                'data_saida_prevista' => now()->addDays(3),
                'status_comercial' => 'reserva_manutencao',
            ]);
        });
    }

    /**
     * 2. TECNOGEN -- Gerador disponível com horímetro perto do limite de
     * preventiva + histórico de teste em banco de carga.
     */
    private function seedTecnogen(Plan $plan): void
    {
        if ($this->alreadySeeded('tecnogen')) {
            return;
        }

        DB::transaction(function () use ($plan) {
            [$tenant, $tecnico, $comercial] = $this->setupTenant('Tecnogen', 'tecnogen', $plan);

            $group = $this->checklistGroup($tenant, 'Geradores de Energia');

            $asset = Asset::create([
                'tenant_id' => $tenant->id,
                'name' => 'Gerador Cummins 500 KVA',
                'tag' => 'GER-CUM500',
                'patrimonio' => 'PAT-CUM-500',
                'serial_number' => 'CUM-500-2026',
                'status' => Asset::STATUS_DISPONIVEL,
                'asset_category' => 'Grupo Gerador Diesel',
                'checklist_group_id' => $group?->id,
                // Item "Troca de óleo do motor" do template tem intervalo de
                // 250h -- 235h deixa a 15h do limite (proximo, nao vencido).
                'horimetro_atual' => 235,
                'last_horimetro' => 235,
            ]);

            MaintenanceOrder::create([
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'technician_id' => $tecnico->id,
                'description' => 'Teste periódico em banco de carga',
                'technical_notes' => 'Testado em banco de carga -- aprovado para operação segura.',
                'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
                'status' => 'Concluída',
                'internal_status' => 'concluido',
                'finished_at' => now()->subDay(),
            ]);

            $supermercado = Client::create(['tenant_id' => $tenant->id, 'name' => 'Supermercado Campinas Centro']);
            $category = AssetCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Grupo Gerador Diesel']);

            SolicitacaoLocacao::create([
                'tenant_id' => $tenant->id,
                'user_id' => $comercial->id,
                'customer_id' => $supermercado->id,
                'category_id' => $category->id,
                'asset_id' => $asset->id,
                'purpose' => 'Chamado de emergência -- blecaute',
                'data_saida_prevista' => now()->addDay(),
                'status_comercial' => 'proposta_em_andamento',
            ]);
        });
    }

    /**
     * 3. CUNZOLO -- Guindaste em manutenção com checklist de segurança já
     * registrado OK + proposta de contrato de escopo longo.
     */
    private function seedCunzolo(Plan $plan): void
    {
        if ($this->alreadySeeded('cunzolo')) {
            return;
        }

        DB::transaction(function () use ($plan) {
            [$tenant, $tecnico] = $this->setupTenant('Cunzolo', 'cunzolo', $plan);

            $group = $this->checklistGroup($tenant, 'Guindastes');

            $asset = Asset::create([
                'tenant_id' => $tenant->id,
                'name' => 'Guindaste Liebherr 100T',
                'tag' => 'GDT-LBH100',
                'patrimonio' => 'PAT-LBH-100',
                'serial_number' => 'LBH-100',
                'status' => Asset::STATUS_MANUTENCAO,
                'asset_category' => 'Guindaste Rodoviário Telescópico',
                'checklist_group_id' => $group?->id,
                'description' => 'Troca de cabos de aço e preventiva',
            ]);

            $order = MaintenanceOrder::create([
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'technician_id' => $tecnico->id,
                'description' => 'Troca de cabos de aço e preventiva',
                'technical_notes' => 'Troca de cabos de aço e preventiva',
                'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
                'status' => 'Em Andamento',
                'internal_status' => 'teste_qualidade',
            ]);

            if ($group) {
                $this->markGroupChecklistOk($tenant, $order, $asset, $group);
            }

            $construtora = Client::create(['tenant_id' => $tenant->id, 'name' => 'Construtora Vale Norte']);

            Contract::create([
                'tenant_id' => $tenant->id,
                'client_id' => $construtora->id,
                'asset_id' => $asset->id,
                'contract_number' => 'CT-CUNZOLO-001',
                'start_date' => now()->addWeek(),
                'usage_purpose' => 'Içamento de estruturas no anel viário',
                'status' => 'Draft',
                'is_active' => false,
                'price' => 45000,
            ]);

            // ContractObserver::created() forca status=locado + client_id em
            // QUALQUER Contrato criado com asset_id (mesmo Draft/inativo),
            // via um model instance PROPRIO (nao o $asset local) -- update()
            // no $asset local nao bastava, pois seu dirty-tracking em memoria
            // ainda achava 'status' inalterado (nunca soube do update feito
            // pelo Observer) e omitia a coluna do SQL. Query builder direto
            // ignora esse estado em memoria e escreve sempre.
            Asset::whereKey($asset->id)->update(['status' => Asset::STATUS_MANUTENCAO, 'client_id' => null]);
        });
    }

    /**
     * 4. SUPERINFRA -- combo de 2 ativos (gerador + mini-carregadeira) numa
     * única solicitação de locação.
     */
    private function seedSuperinfra(Plan $plan): void
    {
        if ($this->alreadySeeded('superinfra')) {
            return;
        }

        DB::transaction(function () use ($plan) {
            [$tenant, , $comercial] = $this->setupTenant('Superinfra', 'superinfra', $plan);

            $geradoresGroup = $this->checklistGroup($tenant, 'Geradores de Energia');

            $gerador = Asset::create([
                'tenant_id' => $tenant->id,
                'name' => 'Gerador 250 KVA',
                'tag' => 'GER-250-SI',
                'patrimonio' => 'PAT-GER-250-SI',
                'status' => Asset::STATUS_DISPONIVEL,
                'asset_category' => 'Grupo Gerador Diesel',
                'checklist_group_id' => $geradoresGroup?->id,
            ]);

            $carregadeira = Asset::create([
                'tenant_id' => $tenant->id,
                'name' => 'Mini-carregadeira Bobcat',
                'tag' => 'MCR-BOBCAT',
                'patrimonio' => 'PAT-BOBCAT-01',
                'status' => Asset::STATUS_MANUTENCAO,
                'asset_category' => 'Mini-carregadeira',
            ]);

            $empreiteira = Client::create(['tenant_id' => $tenant->id, 'name' => 'Empreiteira Rodovia Dom Pedro']);
            $category = AssetCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Grupo Gerador Diesel']);

            $solicitacao = SolicitacaoLocacao::create([
                'tenant_id' => $tenant->id,
                'user_id' => $comercial->id,
                'customer_id' => $empreiteira->id,
                'category_id' => $category->id,
                'purpose' => 'Combo de Canteiro -- Rodovia Dom Pedro',
                'data_saida_prevista' => now()->addDays(5),
                'status_comercial' => 'proposta_em_andamento',
            ]);

            $solicitacao->assets()->attach([$gerador->id, $carregadeira->id]);
        });
    }

    /**
     * 5. RMC PLATAFORMAS -- plataforma acabou de retornar de obra
     * (aguardando triagem), pronta pro checklist de retorno ser concluído
     * ao vivo na demo + contrato já fechado esperando a liberação.
     */
    private function seedRmcPlataformas(Plan $plan): void
    {
        if ($this->alreadySeeded('rmc-plataformas')) {
            return;
        }

        DB::transaction(function () use ($plan) {
            [$tenant, $tecnico] = $this->setupTenant('RMC Plataformas', 'rmc-plataformas', $plan);

            $group = $this->checklistGroup($tenant, 'Plataformas Elevatórias');

            $asset = Asset::create([
                'tenant_id' => $tenant->id,
                'name' => 'Plataforma JLG 450AJ',
                'tag' => 'PLT-JLG450',
                'patrimonio' => 'PAT-JLG-16M',
                'serial_number' => 'JLG-16M-2026',
                'status' => Asset::STATUS_AGUARDANDO_TRIAGEM,
                'asset_category' => 'Plataforma Elevatória Articulada',
                'checklist_group_id' => $group?->id,
                'description' => 'Acabou de retornar de obra',
            ]);

            $order = MaintenanceOrder::create([
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'technician_id' => $tecnico->id,
                'description' => 'Revisão de retorno de obra',
                'maintenance_type' => MaintenanceOrder::TYPE_CHECKIN,
                'status' => 'Em Andamento',
                'internal_status' => 'aguardando_diagnostico',
            ]);

            // Desmobilizacao criada em aberto (nao finalizada) de proposito --
            // o objetivo da demo e completar o checklist AO VIVO e mostrar o
            // ativo virando Disponivel na hora (ver EquipmentMovementMobile::finalize()).
            EquipmentMovement::create([
                'tenant_id' => $tenant->id,
                'maintenance_order_id' => $order->id,
                'asset_id' => $asset->id,
                'type' => EquipmentMovement::TYPE_DESMOBILIZACAO,
                'status' => EquipmentMovement::STATUS_AGUARDANDO_VISTORIA,
            ]);

            $farmaceutica = Client::create(['tenant_id' => $tenant->id, 'name' => 'Indústria Farmacêutica Paulínia']);

            Contract::create([
                'tenant_id' => $tenant->id,
                'client_id' => $farmaceutica->id,
                'asset_id' => $asset->id,
                'contract_number' => 'CT-RMC-001',
                'start_date' => now()->next('Monday'),
                'usage_purpose' => 'Operação em indústria farmacêutica em Paulínia',
                'status' => 'Ativo',
                'is_active' => false,
                'price' => 18500,
            ]);

            // ContractObserver::created() forca status=locado + client_id em
            // QUALQUER Contrato criado com asset_id, via um model instance
            // PROPRIO (nao o $asset local) -- update() no $asset local nao
            // bastava (dirty-tracking em memoria ainda achava 'status'
            // inalterado e omitia a coluna do SQL). Query builder direto
            // ignora esse estado em memoria e escreve sempre.
            Asset::whereKey($asset->id)->update(['status' => Asset::STATUS_AGUARDANDO_TRIAGEM, 'client_id' => null]);
        });
    }

    /**
     * 6. GERADORES CAMPINAS -- gerador alugado com horímetro batendo
     * exatamente no limite de um item de preventiva (250h).
     */
    private function seedGeradoresCampinas(Plan $plan): void
    {
        if ($this->alreadySeeded('geradores-campinas')) {
            return;
        }

        DB::transaction(function () use ($plan) {
            [$tenant] = $this->setupTenant('Geradores Campinas', 'geradores-campinas', $plan);

            $group = $this->checklistGroup($tenant, 'Geradores de Energia');

            $asset = Asset::create([
                'tenant_id' => $tenant->id,
                'name' => 'Gerador MWM 250 KVA',
                'tag' => 'GER-MWM250',
                'patrimonio' => 'PAT-MWM-250',
                'serial_number' => 'MWM-250',
                'status' => Asset::STATUS_LOCADO,
                'asset_category' => 'Gerador Superesilenciado',
                'checklist_group_id' => $group?->id,
                // Bate exatamente no intervalo de 250h do item "Troca de
                // óleo do motor" -- item aparece VENCIDO há 0h.
                'horimetro_atual' => 250,
                'last_horimetro' => 250,
            ]);

            $cliente = Client::create(['tenant_id' => $tenant->id, 'name' => 'Distribuidora Campinas Log']);

            Contract::create([
                'tenant_id' => $tenant->id,
                'client_id' => $cliente->id,
                'asset_id' => $asset->id,
                'contract_number' => 'CT-GERCAMP-001',
                'start_date' => now()->subMonths(2),
                'usage_purpose' => 'Geração de energia em operação contínua',
                'status' => 'Ativo',
                'is_active' => true,
                'price' => 9800,
            ]);
        });
    }

    /**
     * 7. LOMAQ -- mini-escavadeira retornou suja/com avaria, pronta pro
     * laudo em PDF ser gerado.
     */
    private function seedLomaq(Plan $plan): void
    {
        if ($this->alreadySeeded('lomaq')) {
            return;
        }

        DB::transaction(function () use ($plan) {
            [$tenant, $tecnico] = $this->setupTenant('Lomaq', 'lomaq', $plan);

            $asset = Asset::create([
                'tenant_id' => $tenant->id,
                'name' => 'Mini-escavadeira Yanmar ViO17',
                'tag' => 'MEC-YNM17',
                'patrimonio' => 'PAT-YNM-17',
                'serial_number' => 'YNM-17',
                'status' => Asset::STATUS_AGUARDANDO_TRIAGEM,
                'asset_category' => 'Mini-escavadeira Compacta',
                'description' => 'Retornou suja de lama da obra',
            ]);

            $order = MaintenanceOrder::create([
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'technician_id' => $tecnico->id,
                'description' => 'Revisão de retorno de obra',
                'maintenance_type' => MaintenanceOrder::TYPE_CHECKIN,
                'status' => 'Em Andamento',
                'internal_status' => 'aguardando_diagnostico',
            ]);

            $movement = EquipmentMovement::create([
                'tenant_id' => $tenant->id,
                'maintenance_order_id' => $order->id,
                'asset_id' => $asset->id,
                'type' => EquipmentMovement::TYPE_DESMOBILIZACAO,
                'status' => EquipmentMovement::STATUS_EM_ANDAMENTO,
            ]);

            EquipmentDamage::create([
                'tenant_id' => $tenant->id,
                'equipment_movement_id' => $movement->id,
                'maintenance_order_id' => $order->id,
                'asset_id' => $asset->id,
                'reported_by_user_id' => $tecnico->id,
                'severity' => EquipmentDamage::SEVERITY_MODERADA,
                'description' => 'Mangueira hidráulica estourada pelo cliente anterior',
                'requires_replacement' => false,
            ]);
        });
    }

    /**
     * 8. LMA LOCAÇÕES -- 20 unidades idênticas disponíveis, combo/lote
     * numa única solicitação (testa isolamento de tenant em alto volume +
     * ação em massa do AssetResource).
     */
    private function seedLmaLocacoes(Plan $plan): void
    {
        if ($this->alreadySeeded('lma-locacoes')) {
            return;
        }

        DB::transaction(function () use ($plan) {
            [$tenant, , $comercial] = $this->setupTenant('LMA Locações', 'lma-locacoes', $plan);

            $assets = collect(range(1, 20))->map(function (int $i) use ($tenant) {
                $suffix = str_pad((string) $i, 2, '0', STR_PAD_LEFT);

                return Asset::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Conjunto de Solda Aventa Multiprime',
                    'tag' => "SLD-AVT-{$suffix}",
                    'patrimonio' => "PAT-AVT-{$suffix}",
                    'serial_number' => "AVT-MULTIPRIME-{$suffix}",
                    'status' => Asset::STATUS_DISPONIVEL,
                    'asset_category' => 'Inversora de Solda Industrial Multi-processo',
                    'specification' => 'Pátio: Amarais',
                ]);
            });

            $replan = Client::create(['tenant_id' => $tenant->id, 'name' => 'Refinaria Vale do Sul']);
            $category = AssetCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Inversora de Solda Industrial Multi-processo']);

            $solicitacao = SolicitacaoLocacao::create([
                'tenant_id' => $tenant->id,
                'user_id' => $comercial->id,
                'customer_id' => $replan->id,
                'category_id' => $category->id,
                'purpose' => 'Parada de manutenção -- Replan',
                'data_saida_prevista' => now()->addDays(2),
                'status_comercial' => 'proposta_em_andamento',
            ]);

            $solicitacao->assets()->attach($assets->pluck('id'));
        });
    }
}
