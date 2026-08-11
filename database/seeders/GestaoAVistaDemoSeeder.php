<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetDowntimeEvent;
use App\Models\Branch;
use App\Models\HorimeterReading;
use App\Models\MaintenanceOrder;
use App\Models\Tenant;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Enriquecimento do tenant 'geradores-rmc' (ver DemoGeradoresRmcSeeder)
 * especificamente pro dashboard "Gestão à Vista" (App\Services\GestaoAVistaService):
 * mais OS Corretivas/Preventivas espalhadas em 6 meses passados, com
 * failure_category preenchida (coluna nova), AssetDowntimeEvent fechados
 * com datas coerentes com cada OS (não abertos "agora", como o hook
 * automático de MaintenanceOrder::booted() faria sozinho), 2 Branch e
 * variação de custo real por mês -- pra todo KPI do dashboard (MTBF, MTTR,
 * disponibilidade, efetividade, causas de falha, custo) ter dado
 * significativo, não "sem dados"/zero.
 *
 * Idempotente: se o tenant já tem 40+ MaintenanceOrder, assume que este
 * seeder já rodou e não duplica.
 *
 * Uso: php artisan db:seed --class=GestaoAVistaDemoSeeder
 */
class GestaoAVistaDemoSeeder extends Seeder
{
    private const SLUG = 'geradores-rmc';

    private const FAILURE_CATEGORIES = [
        MaintenanceOrder::FAILURE_CATEGORY_HIDRAULICO,
        MaintenanceOrder::FAILURE_CATEGORY_ELETRICO,
        MaintenanceOrder::FAILURE_CATEGORY_MOTOR,
        MaintenanceOrder::FAILURE_CATEGORY_ESTRUTURAL,
        MaintenanceOrder::FAILURE_CATEGORY_OUTRO,
    ];

    // Pesos aproximados: hidráulico e motor são as causas mais comuns em
    // gerador (bloco/óleo/arrefecimento), elétrico moderado, estrutural e
    // outro mais raros -- pro gráfico de barras não sair uniforme demais.
    private const FAILURE_CATEGORY_WEIGHTS = [
        MaintenanceOrder::FAILURE_CATEGORY_HIDRAULICO => 35,
        MaintenanceOrder::FAILURE_CATEGORY_MOTOR => 30,
        MaintenanceOrder::FAILURE_CATEGORY_ELETRICO => 20,
        MaintenanceOrder::FAILURE_CATEGORY_ESTRUTURAL => 10,
        MaintenanceOrder::FAILURE_CATEGORY_OUTRO => 5,
    ];

    private ?Generator $faker = null;

    public function run(): void
    {
        $tenant = Tenant::where('slug', self::SLUG)->first();

        if (! $tenant) {
            $this->command?->error("Tenant '".self::SLUG."' não existe -- rode DemoGeradoresRmcSeeder primeiro.");

            return;
        }

        if (MaintenanceOrder::where('tenant_id', $tenant->id)->count() >= 40) {
            $this->command?->info('Gestão à Vista (Geradores RMC): já rodou antes -- pulando.');

            return;
        }

        $this->command?->info('Gestão à Vista (Geradores RMC): populando histórico de 6 meses...');

        DB::transaction(function () use ($tenant) {
            $branches = $this->seedBranches($tenant);
            $assets = Asset::where('tenant_id', $tenant->id)->get();
            $technicianIds = User::where('tenant_id', $tenant->id)->pluck('id')->all();

            if ($assets->isEmpty() || empty($technicianIds)) {
                $this->command?->warn('Gestão à Vista: sem Asset/User suficiente -- rode DemoGeradoresRmcSeeder completo primeiro.');

                return;
            }

            $this->seedHistoricoMensal($tenant, $assets, $technicianIds, $branches);
            $this->seedHorimeterReadings($tenant, $assets);
        });

        $this->command?->info('Gestão à Vista (Geradores RMC): histórico populado com sucesso.');
    }

    /** @return array<int, Branch> */
    private function seedBranches(Tenant $tenant): array
    {
        $defs = [
            ['name' => 'Oficina Central — Campinas', 'city' => 'Campinas', 'state' => 'SP'],
            ['name' => 'Base Avançada — Sumaré', 'city' => 'Sumaré', 'state' => 'SP'],
        ];

        $branches = [];
        foreach ($defs as $def) {
            $branches[] = Branch::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $def['name']],
                ['city' => $def['city'], 'state' => $def['state']]
            );
        }

        return $branches;
    }

    /**
     * 6 meses corridos (mês atual + 5 anteriores), volume crescente mês a
     * mês (pra série de evolução não ficar plana) e mix de status/tipo
     * variado o suficiente pra todo KPI ter uma leitura real:
     * - Preventivas concluídas em volume alto (sustenta % Manutenção
     *   Realizada perto/acima da meta na maior parte dos meses).
     * - Corretivas concluídas com failure_category preenchida (sustenta
     *   MTTR + Causas de Falha).
     * - Uma fração das corretivas do mesmo ativo dentro de 30 dias
     *   (sustenta Efetividade < 100%, não um número artificialmente
     *   perfeito).
     * - Algumas OS abertas/em andamento no mês corrente (sustenta OS
     *   Planejadas > Concluídas no mês atual, como um mês real em
     *   andamento).
     * - AssetDowntimeEvent fechado por corretiva concluída, com started_at
     *   coerente com a criação da OS e ended_at coerente com finished_at
     *   (sustenta MTBF, Disponibilidade e Tempo de Parada).
     */
    private function seedHistoricoMensal(Tenant $tenant, $assets, array $technicianIds, array $branches): void
    {
        $meses = collect(range(5, 0))->map(fn (int $i) => now()->subMonths($i)->startOfMonth());

        // Guarda por ativo: última OS Corretiva concluída, pra decidir se a
        // próxima corretiva do mesmo ativo cai dentro da janela de
        // retrabalho (30 dias) e assim varia a Efetividade de mês pra mês.
        $ultimaCorretivaPorAsset = [];

        foreach ($meses as $index => $inicioMes) {
            $ehMesAtual = $index === $meses->count() - 1;
            $fimMes = $inicioMes->copy()->endOfMonth();

            // Volume crescente: 6 preventivas/4 corretivas no mês mais antigo,
            // até 11 preventivas/8 corretivas no mês mais recente completo.
            $qtdPreventivas = 6 + $index;
            $qtdCorretivas = 4 + (int) floor($index * 0.8);

            for ($i = 0; $i < $qtdPreventivas; $i++) {
                $this->criarPreventiva($tenant, $assets, $technicianIds, $branches, $inicioMes, $fimMes, $ehMesAtual);
            }

            for ($i = 0; $i < $qtdCorretivas; $i++) {
                $asset = $assets->random();
                $ehRetrabalho = isset($ultimaCorretivaPorAsset[$asset->id])
                    && $this->faker()->boolean(35);

                $criadaEm = $ehRetrabalho
                    ? $ultimaCorretivaPorAsset[$asset->id]->copy()->addDays($this->faker()->numberBetween(3, 25))
                    : $this->dataAleatoriaNoMes($inicioMes, $fimMes);

                // Não deixa a data de retrabalho vazar pro mês seguinte além do
                // fim do período simulado (mês corrente, até agora).
                if ($criadaEm->greaterThan(now())) {
                    $criadaEm = now()->copy()->subHours($this->faker()->numberBetween(1, 48));
                }

                $order = $this->criarCorretiva($tenant, $asset, $technicianIds, $branches, $criadaEm, $ehMesAtual);

                if ($order->status === 'Concluída') {
                    $ultimaCorretivaPorAsset[$asset->id] = $order->finished_at->copy();
                }
            }
        }
    }

    private function dataAleatoriaNoMes($inicioMes, $fimMes): \Illuminate\Support\Carbon
    {
        $limite = $fimMes->greaterThan(now()) ? now() : $fimMes;
        if ($inicioMes->greaterThanOrEqualTo($limite)) {
            return $inicioMes->copy();
        }

        $segundos = $this->faker()->numberBetween(0, $inicioMes->diffInSeconds($limite));

        return $inicioMes->copy()->addSeconds($segundos);
    }

    private function criarPreventiva(Tenant $tenant, $assets, array $technicianIds, array $branches, $inicioMes, $fimMes, bool $ehMesAtual): MaintenanceOrder
    {
        $asset = $assets->random();
        $criadaEm = $this->dataAleatoriaNoMes($inicioMes, $fimMes);

        // No mês corrente, parte das preventivas ainda está em aberto (mês
        // real em andamento) -- nos meses passados, quase todas concluídas
        // (só uma fração pequena cancelada, pra Cancelada existir no dado).
        $roll = $this->faker()->numberBetween(1, 100);
        if ($ehMesAtual && $roll <= 30) {
            $status = 'Aberto';
            $internalStatus = 'aguardando_diagnostico';
        } elseif ($roll <= 6) {
            $status = 'Cancelada';
            $internalStatus = 'aguardando_diagnostico';
        } else {
            $status = 'Concluída';
            $internalStatus = 'concluido';
        }

        $laborCost = $this->faker()->randomFloat(2, 80, 300);
        $materialCost = $this->faker()->randomFloat(2, 0, 400);
        $logisticsCost = $this->faker()->randomFloat(2, 0, 80);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $asset->id,
            'technician_id' => $this->faker()->randomElement($technicianIds),
            'branch_id' => $this->faker()->randomElement($branches)->id,
            'description' => $this->faker()->randomElement([
                'Manutenção preventiva programada (troca de óleo/filtros)',
                'Revisão preventiva conforme plano de horímetro',
                'Verificação/troca de correias e mangueiras',
                'Análise de fluido de arrefecimento e teste de bateria',
            ]),
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'status' => $status,
            'internal_status' => $internalStatus,
            'labor_cost' => $laborCost,
            'material_cost' => $materialCost,
            'logistics_cost' => $logisticsCost,
            'created_at' => $criadaEm,
            'updated_at' => $criadaEm,
        ]);

        if ($status === 'Concluída') {
            $finishedAt = $criadaEm->copy()->addHours($this->faker()->numberBetween(2, 30));
            $order->forceFill(['started_at' => $criadaEm->copy()->addHours(1), 'finished_at' => $finishedAt, 'updated_at' => $finishedAt])->saveQuietly();
            // total_order_cost só é recalculado em updating() com isDirty
            // labor/logistics -- update() real dispara o hook (mesmo padrão
            // já confirmado em GestaoAVistaServiceTest::test_custo_total_sums_orders_in_period).
            $order->update(['labor_cost' => $laborCost, 'logistics_cost' => $logisticsCost]);
        }

        return $order->fresh();
    }

    private function criarCorretiva(Tenant $tenant, Asset $asset, array $technicianIds, array $branches, $criadaEm, bool $ehMesAtual): MaintenanceOrder
    {
        $roll = $this->faker()->numberBetween(1, 100);
        if ($ehMesAtual && $criadaEm->diffInDays(now()) < 3 && $roll <= 40) {
            $status = 'Em Andamento';
            $internalStatus = 'em_manutencao';
        } elseif ($roll <= 5) {
            $status = 'Cancelada';
            $internalStatus = 'aguardando_diagnostico';
        } else {
            $status = 'Concluída';
            $internalStatus = 'concluido';
        }

        $laborCost = $this->faker()->randomFloat(2, 150, 900);
        $materialCost = $this->faker()->randomFloat(2, 50, 1500);
        $logisticsCost = $this->faker()->randomFloat(2, 0, 250);
        $failureCategory = $this->faker()->boolean(85)
            ? $this->weightedFailureCategory()
            : null;

        // MaintenanceOrder::booted()::created() ja cria um AssetDowntimeEvent
        // automatico com started_at=now() -- inevitavel, sera corrigido logo
        // abaixo pra refletir a data historica real da OS.
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $asset->id,
            'technician_id' => $this->faker()->randomElement($technicianIds),
            'branch_id' => $this->faker()->randomElement($branches)->id,
            'description' => $this->faker()->randomElement([
                'Ruído anormal no motor durante operação',
                'Vazamento de óleo hidráulico identificado',
                'Falha no sistema elétrico de partida',
                'Superaquecimento durante uso prolongado',
                'Painel de controle apresentando alarme intermitente',
                'Desgaste excessivo em componente estrutural do chassi',
            ]),
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => $status,
            'internal_status' => $internalStatus,
            'failure_category' => $failureCategory,
            'labor_cost' => $laborCost,
            'material_cost' => $materialCost,
            'logistics_cost' => $logisticsCost,
            'created_at' => $criadaEm,
            'updated_at' => $criadaEm,
        ]);

        $startedAt = $criadaEm->copy()->addHours($this->faker()->numberBetween(1, 6));
        $downtimeEvent = AssetDowntimeEvent::where('maintenance_order_id', $order->id)->first();

        if ($status === 'Concluída') {
            // MTTR realista pro setor: a maioria fecha em horas, uma fração
            // (peça sob encomenda etc) leva vários dias.
            $horasReparo = $this->faker()->boolean(80)
                ? $this->faker()->randomFloat(2, 1, 12)
                : $this->faker()->randomFloat(2, 24, 96);
            $finishedAt = $startedAt->copy()->addHours($horasReparo);

            if ($finishedAt->greaterThan(now())) {
                $finishedAt = now()->copy();
            }

            $order->forceFill(['started_at' => $startedAt, 'finished_at' => $finishedAt, 'updated_at' => $finishedAt])->saveQuietly();
            $order->update(['labor_cost' => $laborCost, 'logistics_cost' => $logisticsCost]);

            if ($downtimeEvent) {
                $downtimeEvent->forceFill(['started_at' => $criadaEm, 'ended_at' => $finishedAt])->saveQuietly();
            }
        } elseif ($status === 'Em Andamento') {
            $order->forceFill(['started_at' => $startedAt])->saveQuietly();

            if ($downtimeEvent) {
                // Continua aberto ate agora -- e' o unico caso onde isso e'
                // realista (parada em curso de verdade), tratado por
                // GestaoAVistaService com teto em now() (bug corrigido
                // anteriormente).
                $downtimeEvent->forceFill(['started_at' => $criadaEm])->saveQuietly();
            }
        } else {
            // Cancelada: fecha o downtime que o hook abriu automaticamente
            // (nao ficou parado de verdade, foi engano/duplicidade).
            if ($downtimeEvent) {
                $downtimeEvent->forceFill(['started_at' => $criadaEm, 'ended_at' => $criadaEm->copy()->addMinutes(30)])->saveQuietly();
            }
        }

        return $order->fresh();
    }

    /**
     * Asset::getMtbfHours() (fonte oficial de MTBF do dashboard) depende
     * de HorimeterReading real (não só do campo horimetro_atual do
     * próprio Asset) -- sem isso o KPI fica sempre null, mesmo com
     * AssetDowntimeEvent fechados de sobra. Uma leitura por mês (6 pontos),
     * progressão de horas coerente com uso diário plausível de gerador em
     * locação -- em vez de 2 leituras com salto único, pra não estourar
     * o limite de salto anômalo do HorimeterReadingObserver (500h por
     * padrão, ver config('oravel.horimeter_jump_threshold')).
     */
    private function seedHorimeterReadings(Tenant $tenant, $assets): void
    {
        foreach ($assets as $asset) {
            if (HorimeterReading::where('asset_id', $asset->id)->exists()) {
                continue;
            }

            $atual = (float) ($asset->horimetro_atual ?: $this->faker()->randomFloat(2, 800, 5000));
            // Uso medio diario plausivel pra gerador em locacao: 3-7h/dia
            // (mais conservador que antes, pro salto mensal ficar < 500h).
            $usoDiario = $this->faker()->randomFloat(2, 3, 7);
            $inicial = max(0, $atual - ($usoDiario * 180));

            $leitura = $inicial;
            for ($mesesAtras = 6; $mesesAtras >= 1; $mesesAtras--) {
                $leitura += $usoDiario * 30;

                HorimeterReading::create([
                    'tenant_id' => $tenant->id,
                    'asset_id' => $asset->id,
                    'reading' => round($leitura, 2),
                    'recorded_at' => now()->subMonths($mesesAtras),
                    'source' => HorimeterReading::SOURCE_MANUAL,
                    'reset_confirmed' => true,
                ]);
            }

            HorimeterReading::create([
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'reading' => round($atual, 2),
                'recorded_at' => now()->subDays($this->faker()->numberBetween(0, 3)),
                'source' => HorimeterReading::SOURCE_MAINTENANCE_ORDER,
                'reset_confirmed' => true,
            ]);
        }
    }

    private function weightedFailureCategory(): string
    {
        $total = array_sum(self::FAILURE_CATEGORY_WEIGHTS);
        $roll = $this->faker()->numberBetween(1, $total);

        $acumulado = 0;
        foreach (self::FAILURE_CATEGORY_WEIGHTS as $categoria => $peso) {
            $acumulado += $peso;
            if ($roll <= $acumulado) {
                return $categoria;
            }
        }

        return MaintenanceOrder::FAILURE_CATEGORY_OUTRO;
    }

    private function faker(): Generator
    {
        return $this->faker ??= FakerFactory::create(config('app.faker_locale', 'pt_BR'));
    }
}
