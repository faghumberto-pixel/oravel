<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\Contract;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Preenche o gap encontrado em 2026-08-06: munkmaq (guindaste/munck) e
 * rmc-plataformas (plataforma elevatória) já existiam via
 * DemoTenantsSeeder, mas com só 1 ativo cada e nenhum plano de manutenção
 * vinculado -- não davam pra demonstrar um painel de frota de verdade.
 * Compressor de Ar não tinha tenant nenhum. Este seeder é aditivo: roda
 * DEPOIS do DemoTenantsSeeder, sem recriar tenant nenhum (usa
 * firstOrCreate/updateOrCreate em tudo), e não mexe no ativo original de
 * cada tenant.
 *
 * NUNCA rodar em produção -- mesmo guard do DemoTenantsSeeder.
 *
 * Uso: php artisan db:seed --class=DemoVerticalGapsSeeder
 */
class DemoVerticalGapsSeeder extends Seeder
{
    public function run(): void
    {
        // Guard original (abort incondicional) trocado por confirmação
        // explícita em 2026-08-06 -- usuário pediu pra popular estes 3
        // tenants fictícios direto em PROD pra demonstração comercial,
        // decisão consciente contra a recomendação padrão do projeto de
        // manter dado de demo só em DEV. Exige a env var
        // ALLOW_DEMO_SEED_IN_PRODUCTION=true (não fica em .env versionado,
        // setar só na hora de rodar) pra não rodar por engano numa chamada
        // futura de `db:seed` genérico em produção.
        if (app()->environment('production') && ! filter_var(env('ALLOW_DEMO_SEED_IN_PRODUCTION'), FILTER_VALIDATE_BOOL)) {
            abort(1, 'DemoVerticalGapsSeeder requer ALLOW_DEMO_SEED_IN_PRODUCTION=true para rodar em produção -- dado fictício de demonstração comercial, ver decisão de 2026-08-06.');
        }

        (new BasicChecklistTemplateSeeder)->run();
        (new PreventiveMaintenanceTemplateSeeder)->run();

        $this->completeMunkmaq();
        $this->completeRmcPlataformas();
        $this->seedCompressorNovo();

        $this->command?->info('DemoVerticalGapsSeeder concluído -- munkmaq/rmc-plataformas completados, compressor-novo criado.');
    }

    /**
     * Munkmaq já tinha 1 munck ("Munck 45T"). Adiciona 4 muncks + 5
     * guindastes (consolidando o vertical guindaste+munck num tenant só,
     * ao invés de manter "cunzolo" separado -- decisão do usuário
     * 2026-08-06) com os mesmos dados usados nos artefatos de vitrine
     * publicados em guindastes.oravel.com.br.
     */
    private function completeMunkmaq(): void
    {
        $tenant = Tenant::where('slug', 'munkmaq')->first();
        if (! $tenant) {
            $this->command?->warn('Tenant munkmaq não existe -- rode DemoTenantsSeeder primeiro. Pulando.');

            return;
        }

        DB::transaction(function () use ($tenant) {
            $munckCategory = AssetCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Caminhão Munck Pesado']);
            $guindasteCategory = AssetCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Guindaste Rodoviário Telescópico']);

            $muncks = [
                ['name' => 'Munck Volvo VM 8x2', 'capacity_value' => 15, 'status' => Asset::STATUS_LOCADO],
                ['name' => 'Munck Mercedes Atego', 'capacity_value' => 8, 'status' => Asset::STATUS_DISPONIVEL, 'veterano' => true],
                ['name' => 'Munck Ford Cargo', 'capacity_value' => 10, 'status' => Asset::STATUS_MANUTENCAO],
                ['name' => 'Munck Volkswagen Constellation', 'capacity_value' => 12, 'status' => Asset::STATUS_DISPONIVEL],
            ];

            foreach ($muncks as $i => $def) {
                Asset::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $def['name']],
                    [
                        // 'tag' é UNIQUE globalmente (não escopada por
                        // tenant) -- prefixo com o slug evita colisão com
                        // ativos de qualquer outro tenant, real ou demo.
                        'tag' => 'MUNKMAQ-CAM-'.($i + 1),
                        'patrimonio' => 'PAT-'.random_int(100000, 999999),
                        'serial_number' => 'SN-'.strtoupper(substr(md5($def['name']), 0, 8)),
                        'status' => $def['status'],
                        'asset_category' => $munckCategory->name,
                        'asset_category_id' => $munckCategory->id,
                        'capacity_value' => $def['capacity_value'],
                        'capacity_unit' => 'ton',
                        'criticality' => ($def['veterano'] ?? false) ? 'high' : 'medium',
                        'criticality_level' => ($def['veterano'] ?? false) ? 'Alta' : 'Média',
                        'horimetro_atual' => ($def['veterano'] ?? false) ? random_int(6000, 15000) : random_int(100, 3000),
                    ]
                );
            }

            $guindastes = [
                ['name' => 'Guindaste Liebherr LTM 1090', 'capacity_value' => 90, 'status' => Asset::STATUS_LOCADO],
                ['name' => 'Guindaste Grove GMK 4100', 'capacity_value' => 100, 'status' => Asset::STATUS_MANUTENCAO, 'veterano' => true],
                ['name' => 'Guindaste Tadano ATF 60G', 'capacity_value' => 60, 'status' => Asset::STATUS_DISPONIVEL],
                ['name' => 'Guindaste Liebherr LTM 1250', 'capacity_value' => 250, 'status' => Asset::STATUS_LOCADO, 'veterano' => true],
                ['name' => 'Guindaste XCMG QY25', 'capacity_value' => 25, 'status' => Asset::STATUS_DISPONIVEL],
            ];

            foreach ($guindastes as $i => $def) {
                Asset::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $def['name']],
                    [
                        'tag' => 'MUNKMAQ-GUI-'.($i + 1),
                        'patrimonio' => 'PAT-'.random_int(100000, 999999),
                        'serial_number' => 'SN-'.strtoupper(substr(md5($def['name']), 0, 8)),
                        'status' => $def['status'],
                        'asset_category' => $guindasteCategory->name,
                        'asset_category_id' => $guindasteCategory->id,
                        'capacity_value' => $def['capacity_value'],
                        'capacity_unit' => 'ton',
                        'criticality' => ($def['veterano'] ?? false) ? 'high' : 'medium',
                        'criticality_level' => ($def['veterano'] ?? false) ? 'Alta' : 'Média',
                        'horimetro_atual' => ($def['veterano'] ?? false) ? random_int(6000, 15000) : random_int(100, 3000),
                    ]
                );
            }

            // Contrato de exemplo pro guindaste Liebherr LTM 1250 (o mesmo
            // cenário mostrado no Caso 02 do artefato de vitrine).
            $liebherr1250 = Asset::where('tenant_id', $tenant->id)->where('name', 'Guindaste Liebherr LTM 1250')->first();
            $construtora = Client::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Construtora Vale Norte']);

            if ($liebherr1250 && ! Contract::where('tenant_id', $tenant->id)->where('asset_id', $liebherr1250->id)->exists()) {
                Contract::create([
                    'tenant_id' => $tenant->id,
                    'client_id' => $construtora->id,
                    'asset_id' => $liebherr1250->id,
                    'contract_number' => 'CT-VN-0012',
                    'start_date' => now()->subMonth(),
                    'usage_purpose' => 'Içamento de estruturas metálicas em obra viária',
                    'status' => 'Ativo',
                    'is_active' => true,
                    'price' => 45000,
                ]);
                // Mesmo bug de ContractObserver documentado no DemoTenantsSeeder
                // -- update() no model em memória não persiste o status.
                Asset::whereKey($liebherr1250->id)->update(['status' => Asset::STATUS_LOCADO]);
            }
        });
    }

    /**
     * RMC Plataformas já tinha 1 plataforma ("JLG 450AJ", aguardando
     * triagem). Adiciona 3 plataformas com os mesmos dados usados nos
     * artefatos de vitrine (plataforma.oravel.com.br).
     */
    private function completeRmcPlataformas(): void
    {
        $tenant = Tenant::where('slug', 'rmc-plataformas')->first();
        if (! $tenant) {
            $this->command?->warn('Tenant rmc-plataformas não existe -- rode DemoTenantsSeeder primeiro. Pulando.');

            return;
        }

        DB::transaction(function () use ($tenant) {
            $category = AssetCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Plataforma Elevatória Articulada']);
            $categoryTesoura = AssetCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Plataforma Elevatória Tesoura']);

            $plataformas = [
                ['name' => 'Tesoura JLG 3369E', 'capacity_value' => 12, 'status' => Asset::STATUS_DISPONIVEL, 'category' => $categoryTesoura],
                ['name' => 'Articulada Genie Z-60', 'capacity_value' => 18, 'status' => Asset::STATUS_LOCADO, 'veterano' => true, 'category' => $category],
                ['name' => 'Tesoura Haulotte Compact 12', 'capacity_value' => 12, 'status' => Asset::STATUS_DISPONIVEL, 'category' => $categoryTesoura],
            ];

            foreach ($plataformas as $i => $def) {
                Asset::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $def['name']],
                    [
                        'tag' => 'RMCPLAT-PLA-'.($i + 2),
                        'patrimonio' => 'PAT-'.random_int(100000, 999999),
                        'serial_number' => 'SN-'.strtoupper(substr(md5($def['name']), 0, 8)),
                        'status' => $def['status'],
                        'asset_category' => $def['category']->name,
                        'asset_category_id' => $def['category']->id,
                        'capacity_value' => $def['capacity_value'],
                        'capacity_unit' => 'm',
                        'criticality' => ($def['veterano'] ?? false) ? 'high' : 'medium',
                        'criticality_level' => ($def['veterano'] ?? false) ? 'Alta' : 'Média',
                        'horimetro_atual' => ($def['veterano'] ?? false) ? random_int(1500, 2000) : random_int(300, 700),
                    ]
                );
            }
        });
    }

    /**
     * Compressor de Ar não tinha nenhum tenant -- cria um novo, mesmo
     * padrão de dados usados no artefato de vitrine (compressor.oravel.com.br).
     */
    private function seedCompressorNovo(): void
    {
        if (Tenant::where('slug', 'compressor-novo')->exists()) {
            $this->command?->info("Tenant 'compressor-novo' já existe -- pulando (idempotente).");

            return;
        }

        DB::transaction(function () {
            $plan = Plan::firstOrCreate(
                ['name' => 'Plano Demo Comercial'],
                [
                    'price' => 1999, 'base_price' => 1999, 'level' => 3, 'billing_cycle' => 'monthly',
                    'is_active' => true,
                    'features' => [
                        'tabela_assets', 'tabela_asset_categories', 'tabela_clients', 'tabela_contracts',
                        'tabela_maintenance_orders', 'tabela_maintenance_plans', 'tabela_roles', 'tabela_users',
                        'tabela_checklist_groups', 'tabela_checklist_templates',
                    ],
                ]
            );

            $tenant = Tenant::create([
                'name' => 'Compressor Novo',
                'slug' => 'compressor-novo',
                'status' => 'active',
                'plan_id' => $plan->id,
                'onboarding_completed' => true,
            ]);

            TenantProvisioner::provision($tenant, [
                'name' => 'Admin Compressor Novo',
                'email' => 'admin@compressor-novo.com.br',
                'password' => 'demo12345',
            ]);

            $tecnicoRole = Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
            $tecnico = User::create([
                'name' => 'Técnico Compressor Novo',
                'email' => 'tecnico@compressor-novo.com.br',
                'password' => Hash::make('demo12345'),
                'tenant_id' => $tenant->id,
                'role' => 'tecnico',
            ]);
            $tecnico->forceFill(['email_verified_at' => now()])->save();
            $tecnico->assignRole($tecnicoRole);

            $category = AssetCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Compressores de Ar']);

            $atlasCopco = Asset::create([
                'tenant_id' => $tenant->id,
                'name' => 'Compressor de Ar Atlas Copco XATS 400',
                'tag' => 'COMPNOVO-COM-1',
                'patrimonio' => 'PAT-'.random_int(100000, 999999),
                'serial_number' => 'SN-ATLAS400',
                'status' => Asset::STATUS_DISPONIVEL,
                'asset_category' => $category->name,
                'asset_category_id' => $category->id,
                'capacity_value' => 400,
                'capacity_unit' => 'pcm',
                'criticality' => 'medium',
                'criticality_level' => 'Média',
                'horimetro_atual' => 1240,
                'manufacturing_year' => 2024,
                'acquisition_value' => 198000,
            ]);

            $chicagoPneumatic = Asset::create([
                'tenant_id' => $tenant->id,
                'name' => 'Compressor de Ar Chicago Pneumatic CPS 185',
                'tag' => 'COMPNOVO-COM-2',
                'patrimonio' => 'PAT-'.random_int(100000, 999999),
                'serial_number' => 'CP18500427',
                'status' => Asset::STATUS_LOCADO,
                'asset_category' => $category->name,
                'asset_category_id' => $category->id,
                'capacity_value' => 185,
                'capacity_unit' => 'pcm',
                'criticality' => 'high',
                'criticality_level' => 'Alta',
                'horimetro_atual' => 9840,
                'manufacturing_year' => 2017,
                'acquisition_value' => 312000,
            ]);

            MaintenanceOrder::create([
                'tenant_id' => $tenant->id,
                'asset_id' => $chicagoPneumatic->id,
                'technician_id' => $tecnico->id,
                'description' => 'Vistoria NR-13 e troca de filtro separador de óleo',
                'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
                'status' => 'Aberto',
                'internal_status' => 'aguardando_diagnostico',
            ]);

            $construtora = Client::create(['tenant_id' => $tenant->id, 'name' => 'Construtora Norte Campinas']);

            Contract::create([
                'tenant_id' => $tenant->id,
                'client_id' => $construtora->id,
                'asset_id' => $chicagoPneumatic->id,
                'contract_number' => 'CT-CMP-0002',
                'start_date' => now()->subMonth(),
                'usage_purpose' => 'Locação para obra — ar comprimido para ferramentas pneumáticas',
                'status' => 'Ativo',
                'is_active' => true,
                'price' => 3400,
            ]);
            Asset::whereKey($chicagoPneumatic->id)->update(['status' => Asset::STATUS_LOCADO]);
        });
    }
}
