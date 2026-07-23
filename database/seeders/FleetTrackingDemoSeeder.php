<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetDowntimeEvent;
use App\Models\ChecklistGroup;
use App\Models\HorimeterReading;
use App\Models\MaintenancePlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Popula os 3 módulos novos de frota (horímetro, planos por template,
 * histórico de paradas) nos 5 tenants de demonstração já existentes --
 * mesmo espírito do QuoteAndFinanceiroDemoSeeder: aditivo, idempotente,
 * sem criar tenant novo.
 *
 * Uso: php artisan db:seed --class=FleetTrackingDemoSeeder
 */
class FleetTrackingDemoSeeder extends Seeder
{
    private const SLUGS = [
        'torres-guindastes',
        'geradores-rmc',
        'construtora-alicerce-locacoes',
        'eventos-show-geradores',
        'hospital-vida-plena-energia',
    ];

    /**
     * Os 3 tenants sem nenhum Grupo de Checklist ainda (só geradores) --
     * ganham o grupo "Geradores de Energia" + o template padrão via
     * PreventiveMaintenanceTemplateSeeder (mesma tabela ITEMS_BY_GROUP já
     * usada por Torres/Geradores RMC).
     */
    private const TENANTS_SEM_GRUPO = [
        'construtora-alicerce-locacoes',
        'eventos-show-geradores',
        'hospital-vida-plena-energia',
    ];

    public function run(): void
    {
        foreach (self::TENANTS_SEM_GRUPO as $slug) {
            $this->ensureGeradoresGroup($slug);
        }

        // Reaproveita o template já existente (idempotente, roda pra todos
        // os tenants) -- agora os 3 que acabaram de ganhar o grupo entram
        // no critério dele também.
        (new PreventiveMaintenanceTemplateSeeder)->run();

        foreach (self::SLUGS as $slug) {
            $tenant = Tenant::where('slug', $slug)->first();

            if (! $tenant) {
                $this->command?->warn("Tenant '{$slug}' não encontrado -- pulando.");

                continue;
            }

            $this->seedHorimeterHistory($tenant);
            $this->seedTemplateOverride($tenant);
            $this->seedDowntimeEvents($tenant);
        }
    }

    private function ensureGeradoresGroup(string $slug): void
    {
        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant) {
            return;
        }

        $group = ChecklistGroup::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Geradores de Energia'],
            ['description' => 'Geradores diesel -- preventiva por horímetro']
        );

        Asset::where('tenant_id', $tenant->id)
            ->whereNull('checklist_group_id')
            ->update(['checklist_group_id' => $group->id]);
    }

    /**
     * Histórico de 4 leituras (últimos ~45 dias) pros 2 ativos com
     * patrimônio de cada tenant -- os que já tinham horimetro_atual
     * herdado de seeders anteriores. A última leitura bate com o valor
     * que já existia (nunca regride, mesmo critério do Observer).
     */
    private function seedHorimeterHistory(Tenant $tenant): void
    {
        $admin = User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();

        $assets = Asset::where('tenant_id', $tenant->id)
            ->whereNotNull('patrimonio')
            ->where('horimetro_atual', '>', 0)
            ->orderByDesc('horimetro_atual')
            ->limit(2)
            ->get();

        foreach ($assets as $asset) {
            // Idempotência por ATIVO, não por tenant -- um ativo pode já
            // ter apontamento orgânico (ex: técnico usou a feature de
            // verdade antes deste seeder rodar, via horimetro_entry numa
            // O.S.) enquanto outro do mesmo tenant nunca teve nenhum. Pular
            // o tenant inteiro deixava esse segundo ativo sem histórico.
            if ($asset->horimeterReadings()->exists()) {
                continue;
            }

            $atual = (float) $asset->horimetro_atual;
            // 4 leituras crescentes terminando no valor que já existia --
            // ~15% do total em cada uma das 3 primeiras, resto na última.
            $marcos = [
                round($atual * 0.55, 2),
                round($atual * 0.70, 2),
                round($atual * 0.85, 2),
                $atual,
            ];
            $dias = [45, 30, 15, 2];

            foreach ($marcos as $i => $reading) {
                HorimeterReading::create([
                    'tenant_id' => $tenant->id,
                    'asset_id' => $asset->id,
                    'reading' => $reading,
                    'recorded_at' => now()->subDays($dias[$i]),
                    'recorded_by' => $admin?->id,
                    'source' => HorimeterReading::SOURCE_MANUAL,
                    // Backfill de histórico legitimamente pula mais que o
                    // limite normal entre leituras -- não é um erro de
                    // digitação real pra confirmar aqui.
                    'reset_confirmed' => true,
                ]);
            }
        }
    }

    /**
     * Personaliza 1 item do template do Grupo pro ativo mais crítico do
     * tenant -- demonstra a "cópia editável por ativo" sem duplicar alerta
     * (MaintenancePlan::applicableFor()).
     */
    private function seedTemplateOverride(Tenant $tenant): void
    {
        $asset = Asset::where('tenant_id', $tenant->id)
            ->whereNotNull('checklist_group_id')
            ->orderByDesc('horimetro_atual')
            ->first();

        if (! $asset) {
            return;
        }

        if ($asset->maintenancePlans()->exists()) {
            return;
        }

        $itemDoGrupo = MaintenancePlan::where('checklist_group_id', $asset->checklist_group_id)
            ->where('name', 'Troca de óleo do motor')
            ->first();

        if (! $itemDoGrupo) {
            return;
        }

        $copia = $asset->copyMaintenancePlanTemplateItem($itemDoGrupo);
        // Intervalo mais curto pra este ativo especificamente (ex: opera em
        // ambiente mais pesado) -- é exatamente o que a personalização
        // existe pra permitir, sem mexer no template do grupo. last_service_hours
        // é coluna integer (legado, ver migration original) -- trunca.
        $copia->update(['interval_hours' => 200, 'last_service_hours' => (int) $asset->horimetro_atual]);
    }

    /**
     * 2 paradas por tenant: 1 já encerrada (histórico) + 1 em aberto
     * (pra aparecer na tela "em aberto" e testar a ação Encerrar).
     */
    private function seedDowntimeEvents(Tenant $tenant): void
    {
        if (AssetDowntimeEvent::where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        $admin = User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();
        $assets = Asset::where('tenant_id', $tenant->id)->limit(2)->get();

        if ($assets->count() < 1) {
            return;
        }

        AssetDowntimeEvent::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $assets->first()->id,
            'started_at' => now()->subDays(10),
            'ended_at' => now()->subDays(9)->subHours(4),
            'reason' => AssetDowntimeEvent::REASON_AGUARDANDO_PECA,
            'notes' => 'Aguardando peça de reposição do fornecedor.',
            'registered_by' => $admin?->id,
        ]);

        if ($assets->count() > 1) {
            AssetDowntimeEvent::create([
                'tenant_id' => $tenant->id,
                'asset_id' => $assets->get(1)->id,
                'started_at' => now()->subHours(6),
                'reason' => AssetDowntimeEvent::REASON_QUEBRA,
                'notes' => 'Parada não planejada -- em diagnóstico.',
                'registered_by' => $admin?->id,
            ]);
        }
    }
}
