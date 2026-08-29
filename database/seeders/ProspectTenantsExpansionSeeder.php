<?php

namespace Database\Seeders;

use App\Domain\Fleet\Models\RentalHourFranchise;
use App\Domain\Fleet\Models\RentalOverageCharge;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\Contract;
use App\Models\CrmLead;
use App\Models\EquipmentDamage;
use App\Models\HorimeterReading;
use App\Models\InternalUnit;
use App\Models\MaintenanceOrder;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\StorageLocation;
use App\Models\Tenant;
use App\Models\TechnicianAllocation;
use App\Models\User;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Segunda rodada de dados de demonstração para Alumaq/Gêmeos
 * Guindastes/Eletraq (ver ProspectTenantsDemoSeeder para a primeira
 * rodada -- clients/contracts/OS/materiais/fornecedores/CRM). Cobre:
 * Branch, InternalUnit, StorageLocation (planta baixa), TechnicianAllocation
 * (com técnicos propositalmente NÃO alocados), Quote ligado a avarias
 * "em cobrança" (ponte real, antes só existia o status sem orçamento por
 * trás), PropostaComercial (ligada a CrmLead), Quote avulso (orçamento
 * comercial sem OS/avaria), RentalHourFranchise + RentalOverageCharge
 * (só em contratos com billing_type = franquia_excedente -- este seeder
 * converte alguns contratos existentes pra essa modalidade).
 *
 * Não popula Employee (resolvido via comando `tenant:backfill-employees
 * --apply`, já existente e genérico -- não precisa de seeder dedicado).
 *
 * Idempotente por tenant: se o tenant já tem TechnicianAllocation, pula
 * (mesmo padrão do ProspectTenantsDemoSeeder).
 *
 * Uso: php artisan db:seed --class=ProspectTenantsExpansionSeeder
 */
class ProspectTenantsExpansionSeeder extends Seeder
{
    private const SLUGS = ['alumaq', 'gemeos-guindastes', 'eletraq'];

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
        if (TechnicianAllocation::where('tenant_id', $tenant->id)->exists()) {
            $this->command?->info("Tenant '{$tenant->slug}' já tem alocações -- pulando (idempotente).");

            return;
        }

        $this->command?->info("Semeando dados de expansão para '{$tenant->name}'...");

        DB::transaction(function () use ($tenant) {
            $branches = $this->seedBranches($tenant);
            $units = $this->seedInternalUnits($tenant, $branches);
            $this->seedStorageLocations($tenant, $units);
            $this->seedAssetCategories($tenant);
            $this->seedTechnicianAllocations($tenant);
            $this->seedDamageQuotes($tenant);
            $this->seedPropostasComerciais($tenant);
            $this->seedStandaloneQuotes($tenant);
            $this->seedHourFranchiseAndOverage($tenant);
        });

        $this->command?->info("Tenant '{$tenant->name}' (expansão) concluído.");
    }

    private function faker(): Generator
    {
        static $faker;

        return $faker ??= Factory::create(config('app.faker_locale', 'pt_BR'));
    }

    /** @return Collection<int, Branch> */
    private function seedBranches(Tenant $tenant): Collection
    {
        $defs = [
            ['name' => 'Matriz', 'city' => 'Campinas', 'state' => 'SP'],
            ['name' => 'Filial Sul', 'city' => 'Curitiba', 'state' => 'PR'],
        ];

        return collect($defs)->map(fn (array $def) => Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $def['name']],
            ['city' => $def['city'], 'state' => $def['state'], 'description' => 'Unidade '.$def['name']]
        ));
    }

    /** @return Collection<int, InternalUnit> */
    private function seedInternalUnits(Tenant $tenant, Collection $branches): Collection
    {
        $defs = [
            ['name' => 'Pátio Central', 'type' => 'patio', 'city' => 'Campinas', 'state' => 'SP'],
            ['name' => 'Almoxarifado Central', 'type' => 'almoxarifado', 'city' => 'Campinas', 'state' => 'SP'],
        ];

        return collect($defs)->map(fn (array $def) => InternalUnit::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $def['name']],
            [
                'type' => $def['type'],
                'city' => $def['city'],
                'state' => $def['state'],
                'is_active' => true,
            ]
        ));
    }

    private function seedStorageLocations(Tenant $tenant, Collection $units): void
    {
        $patio = $units->firstWhere('type', 'patio');
        $almoxarifado = $units->firstWhere('type', 'almoxarifado');

        if ($patio) {
            for ($row = 1; $row <= 3; $row++) {
                for ($col = 1; $col <= 4; $col++) {
                    StorageLocation::firstOrCreate(
                        ['tenant_id' => $tenant->id, 'internal_unit_id' => $patio->id, 'row' => $row, 'column' => $col, 'context' => StorageLocation::CONTEXT_PATIO_ATIVOS],
                        ['code' => "P-{$row}{$col}", 'label' => "Vaga {$row}-{$col}", 'is_active' => true]
                    );
                }
            }
        }

        if ($almoxarifado) {
            for ($row = 1; $row <= 2; $row++) {
                for ($col = 1; $col <= 5; $col++) {
                    StorageLocation::firstOrCreate(
                        ['tenant_id' => $tenant->id, 'internal_unit_id' => $almoxarifado->id, 'row' => $row, 'column' => $col, 'context' => StorageLocation::CONTEXT_ALMOXARIFADO],
                        ['code' => "A-{$row}{$col}", 'label' => "Prateleira {$row}-{$col}", 'is_active' => true]
                    );
                }
            }
        }
    }

    private function seedAssetCategories(Tenant $tenant): void
    {
        // Complementa o fallback "Equipamentos Diversos" já criado pelo
        // ProspectTenantsDemoSeeder -- categorias adicionais pra dar mais
        // opções de distribuição no PMP.
        $names = ['Ferramentas Elétricas', 'Ferramentas Manuais', 'Equipamentos de Solda', 'Equipamentos de Elevação', 'Equipamentos de Movimentação'];

        foreach ($names as $name) {
            AssetCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $name]);
        }
    }

    /**
     * Deixa propositalmente alguns técnicos SEM alocação -- pedido
     * explícito do usuário, pra tela de alocação mostrar quem está livre.
     */
    private function seedTechnicianAllocations(Tenant $tenant): void
    {
        $technicians = User::where('tenant_id', $tenant->id)->where('role', 'tecnico')->get();

        if ($technicians->isEmpty()) {
            return;
        }

        // Só aloca ~60% dos técnicos -- o resto fica livre de propósito.
        $toAllocate = $technicians->random((int) ceil($technicians->count() * 0.6));
        $orders = MaintenanceOrder::where('tenant_id', $tenant->id)->inRandomOrder()->limit(10)->get();

        if ($orders->isEmpty()) {
            return;
        }

        $statuses = [TechnicianAllocation::STATUS_PLANEJADO, TechnicianAllocation::STATUS_CONFIRMADO, TechnicianAllocation::STATUS_CONCLUIDO];

        foreach ($toAllocate as $i => $technician) {
            $order = $orders->random();
            $start = $this->faker()->dateTimeBetween('-10 days', '+10 days');

            TechnicianAllocation::create([
                'tenant_id' => $tenant->id,
                'technician_id' => $technician->id,
                'maintenance_order_id' => $order->id,
                'starts_at' => $start,
                'ends_at' => (clone $start)->modify('+'.$this->faker()->numberBetween(2, 8).' hours'),
                'status' => $statuses[$i % count($statuses)],
                'delivery_mode' => $this->faker()->boolean(70) ? TechnicianAllocation::DELIVERY_DIGITAL : TechnicianAllocation::DELIVERY_IMPRESSA,
            ]);
        }
    }

    /**
     * Completa a ponte real entre avaria "em cobrança" e o orçamento
     * indenizatório que deveria existir por trás dela -- antes o status
     * existia sem nenhum Quote vinculado.
     */
    private function seedDamageQuotes(Tenant $tenant): void
    {
        $damages = EquipmentDamage::where('tenant_id', $tenant->id)
            ->where('status', EquipmentDamage::STATUS_EM_COBRANCA)
            ->get();

        if ($damages->isEmpty()) {
            return;
        }

        $reviewer = User::where('tenant_id', $tenant->id)->where('role', 'colaborador')->first();

        foreach ($damages as $damage) {
            if ($damage->quotes()->exists()) {
                continue;
            }

            $total = $this->faker()->randomFloat(2, 300, 6000);

            $quote = Quote::create([
                'tenant_id' => $tenant->id,
                'quotable_type' => EquipmentDamage::class,
                'quotable_id' => $damage->id,
                'client_id' => $damage->maintenanceOrder?->client_id,
                'assigned_user_id' => $reviewer?->id,
                'type' => Quote::TYPE_INDENIZATORIO,
                'status' => Quote::STATUS_ENVIADO,
                'total_value' => $total,
                'sent_at' => now()->subDays($this->faker()->numberBetween(1, 15)),
            ]);

            QuoteItem::create([
                'tenant_id' => $tenant->id,
                'quote_id' => $quote->id,
                'type' => QuoteItem::TYPE_SERVICO,
                'description' => 'Reparo de avaria em equipamento',
                'quantity' => 1,
                'unit_price' => $total,
                'subtotal' => $total,
            ]);
        }
    }

    private function seedPropostasComerciais(Tenant $tenant): void
    {
        $leads = CrmLead::where('tenant_id', $tenant->id)->get();
        $seller = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($q) => $q->where('name', EquipmentDamage::ROLE_COMERCIAL))
            ->first();

        if ($leads->isEmpty() || ! $seller) {
            return;
        }

        $statuses = [PropostaComercial::STATUS_RASCUNHO, PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL, PropostaComercial::STATUS_APROVADA, PropostaComercial::STATUS_REJEITADA];

        foreach ($leads->take(3) as $i => $lead) {
            $totalValue = $this->faker()->randomFloat(2, 2000, 25000);

            $proposta = PropostaComercial::create([
                'tenant_id' => $tenant->id,
                'crm_lead_id' => $lead->id,
                'client_id' => null,
                'seller_user_id' => $seller->id,
                'status' => $statuses[$i % count($statuses)],
                'valid_until' => now()->addDays(30),
                'total_value' => $totalValue,
            ]);

            PropostaComercialItem::create([
                'tenant_id' => $tenant->id,
                'proposta_comercial_id' => $proposta->id,
                'type' => PropostaComercialItem::TYPE_EQUIPAMENTO,
                'description' => $lead->equipment_interest ?? 'Locação de equipamento',
                'quantity' => 1,
                'unit_price' => $totalValue,
                'unit_period' => 'mensal',
                'subtotal' => $totalValue,
            ]);
        }
    }

    private function seedStandaloneQuotes(Tenant $tenant): void
    {
        $clients = \App\Models\Client::where('tenant_id', $tenant->id)->get();
        $seller = User::where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($q) => $q->where('name', EquipmentDamage::ROLE_COMERCIAL))
            ->first();

        if ($clients->isEmpty()) {
            return;
        }

        foreach ($clients->random(min(3, $clients->count())) as $client) {
            $total = $this->faker()->randomFloat(2, 500, 8000);

            $quote = Quote::create([
                'tenant_id' => $tenant->id,
                'quotable_type' => null,
                'quotable_id' => null,
                'client_id' => $client->id,
                'assigned_user_id' => $seller?->id,
                'type' => Quote::TYPE_TERCEIRO,
                'status' => Quote::STATUS_APROVADO,
                'total_value' => $total,
                'sent_at' => now()->subDays($this->faker()->numberBetween(1, 20)),
            ]);

            QuoteItem::create([
                'tenant_id' => $tenant->id,
                'quote_id' => $quote->id,
                'type' => QuoteItem::TYPE_SERVICO,
                'description' => 'Orçamento de locação/serviço avulso',
                'quantity' => 1,
                'unit_price' => $total,
                'subtotal' => $total,
            ]);
        }
    }

    /**
     * Converte alguns contratos existentes pra billing_type =
     * franquia_excedente (só faz sentido nessa modalidade), cria a
     * franquia e alguns lançamentos de excedente com leituras de
     * horímetro por trás.
     */
    private function seedHourFranchiseAndOverage(Tenant $tenant): void
    {
        $contracts = Contract::where('tenant_id', $tenant->id)->whereNotNull('asset_id')->get();

        if ($contracts->isEmpty()) {
            return;
        }

        $picked = $contracts->random(min(3, $contracts->count()));

        foreach ($picked as $contract) {
            $contract->update(['billing_type' => Contract::BILLING_FRANQUIA_EXCEDENTE]);

            $franchise = RentalHourFranchise::create([
                'tenant_id' => $tenant->id,
                'contract_id' => $contract->id,
                'included_hours_per_period' => 200,
                'period_type' => 'mensal',
                'overage_rate_per_hour' => $this->faker()->randomFloat(2, 15, 60),
                'effective_from' => $contract->created_at ?? now()->subMonths(1),
            ]);

            $asset = $contract->asset;
            if (! $asset) {
                continue;
            }

            $periodStart = now()->subDays(30)->startOfDay();
            $periodEnd = now()->startOfDay();

            // Segunda leitura = primeira + horas usadas, pra respeitar o
            // limite de salto de HorimeterReadingObserver::creating()
            // (config('oravel.horimeter_jump_threshold'), default 500h).
            $readingStart = $this->faker()->randomFloat(2, 1000, 2000);
            $hoursUsed = $this->faker()->randomFloat(2, 180, 280);
            $readingEnd = $readingStart + $hoursUsed;

            HorimeterReading::create([
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'reading' => $readingStart,
                'recorded_at' => $periodStart,
                'source' => 'manual',
            ]);

            HorimeterReading::create([
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'reading' => $readingEnd,
                'recorded_at' => $periodEnd,
                'source' => 'manual',
            ]);

            $hoursIncluded = (float) $franchise->included_hours_per_period;
            $hoursOverage = max(0, $hoursUsed - $hoursIncluded);

            RentalOverageCharge::create([
                'tenant_id' => $tenant->id,
                'contract_id' => $contract->id,
                'asset_id' => $asset->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'hours_used' => $hoursUsed,
                'hours_included' => $hoursIncluded,
                'hours_overage' => $hoursOverage,
                'amount' => round($hoursOverage * (float) $franchise->overage_rate_per_hour, 2),
                'status' => $hoursOverage > 0
                    ? RentalOverageCharge::STATUS_PENDING
                    : RentalOverageCharge::STATUS_CANCELLED,
            ]);
        }
    }
}
