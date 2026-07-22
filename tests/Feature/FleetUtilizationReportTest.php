<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetDowntimeEvent;
use App\Models\HorimeterReading;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Item 4 do pedido de 2026-07-22 (3 módulos de frota): fleet:utilization-report
 * -- horas trabalhadas (delta de horímetro no período), horas paradas
 * (soma de duration recortada pro período) e taxa de utilização por ativo.
 */
class FleetUtilizationReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Relatorio '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_horimeter_readings', 'tabela_asset_downtime_events'],
        ]);

        return Tenant::create([
            'name' => 'Tenant Relatorio '.uniqid(), 'slug' => 'tenant-relatorio-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    private function makeAsset(Tenant $tenant): Asset
    {
        return Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Utilização', 'tag' => 'AST-'.uniqid(),
            'patrimonio' => 'PAT-'.uniqid(), 'status' => 'disponivel',
        ]);
    }

    private function makeUser(Tenant $tenant): User
    {
        $user = User::create([
            'name' => 'Tecnico', 'email' => 'tec-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    public function test_computes_worked_hours_downtime_hours_and_utilization_rate(): void
    {
        $tenant = $this->makeTenant();
        $asset = $this->makeAsset($tenant);
        $user = $this->makeUser($tenant);
        $month = Carbon::parse('2026-06-01');

        HorimeterReading::withoutEvents(function () use ($tenant, $asset, $user) {
            HorimeterReading::create([
                'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
                'reading' => 1000, 'recorded_at' => '2026-06-02 08:00:00', 'recorded_by' => $user->id,
            ]);
            HorimeterReading::create([
                'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
                'reading' => 1300, 'recorded_at' => '2026-06-28 17:00:00', 'recorded_by' => $user->id,
            ]);
        });

        // 10h de parada por quebra dentro do período (bem dentro do mês, sem
        // precisar de clipping nas bordas).
        AssetDowntimeEvent::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'started_at' => '2026-06-10 08:00:00', 'ended_at' => '2026-06-10 18:00:00',
            'reason' => AssetDowntimeEvent::REASON_QUEBRA,
        ]);

        $exitCode = Artisan::call('fleet:utilization-report', ['--month' => '2026-06']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Gerador Utilização', $output);
        $this->assertStringContainsString('300,0h', $output);
        $this->assertStringContainsString('10,0h', $output);
        $this->assertStringContainsString('96,8%', $output);
    }

    public function test_downtime_spanning_outside_the_period_is_clipped_to_it(): void
    {
        $tenant = $this->makeTenant();
        $asset = $this->makeAsset($tenant);
        $user = $this->makeUser($tenant);

        HorimeterReading::withoutEvents(function () use ($tenant, $asset, $user) {
            HorimeterReading::create([
                'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
                'reading' => 100, 'recorded_at' => '2026-06-01 08:00:00', 'recorded_by' => $user->id,
            ]);
            HorimeterReading::create([
                'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
                'reading' => 200, 'recorded_at' => '2026-06-30 08:00:00', 'recorded_by' => $user->id,
            ]);
        });

        // Comecou 12h antes do mes e terminou dentro dele -- só as horas
        // DENTRO de junho devem contar (12h, nao 24h).
        AssetDowntimeEvent::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'started_at' => '2026-05-31 12:00:00', 'ended_at' => '2026-06-01 12:00:00',
            'reason' => AssetDowntimeEvent::REASON_AGUARDANDO_PECA,
        ]);

        $this->artisan('fleet:utilization-report', ['--month' => '2026-06'])
            ->assertSuccessful()
            ->expectsOutputToContain('12,0h');
    }

    public function test_asset_without_enough_data_is_excluded_from_the_report(): void
    {
        $tenant = $this->makeTenant();
        $this->makeAsset($tenant);

        $this->artisan('fleet:utilization-report', ['--month' => '2026-06'])
            ->assertSuccessful()
            ->expectsOutputToContain('Período:')
            ->doesntExpectOutputToContain('Gerador Utilização');
    }

    public function test_tenant_option_filters_to_a_single_tenant(): void
    {
        $tenantA = $this->makeTenant();
        $assetA = $this->makeAsset($tenantA);
        $userA = $this->makeUser($tenantA);
        HorimeterReading::withoutEvents(function () use ($tenantA, $assetA, $userA) {
            HorimeterReading::create(['tenant_id' => $tenantA->id, 'asset_id' => $assetA->id, 'reading' => 10, 'recorded_at' => '2026-06-01', 'recorded_by' => $userA->id]);
            HorimeterReading::create(['tenant_id' => $tenantA->id, 'asset_id' => $assetA->id, 'reading' => 20, 'recorded_at' => '2026-06-15', 'recorded_by' => $userA->id]);
        });

        $tenantB = $this->makeTenant();
        $assetB = Asset::create(['tenant_id' => $tenantB->id, 'name' => 'Ativo Tenant B', 'tag' => 'AST-'.uniqid(), 'status' => 'disponivel']);
        $userB = $this->makeUser($tenantB);
        HorimeterReading::withoutEvents(function () use ($tenantB, $assetB, $userB) {
            HorimeterReading::create(['tenant_id' => $tenantB->id, 'asset_id' => $assetB->id, 'reading' => 10, 'recorded_at' => '2026-06-01', 'recorded_by' => $userB->id]);
            HorimeterReading::create(['tenant_id' => $tenantB->id, 'asset_id' => $assetB->id, 'reading' => 20, 'recorded_at' => '2026-06-15', 'recorded_by' => $userB->id]);
        });

        $this->artisan('fleet:utilization-report', ['--month' => '2026-06', '--tenant' => $tenantA->slug])
            ->assertSuccessful()
            ->expectsOutputToContain($tenantA->name)
            ->doesntExpectOutputToContain($tenantB->name);
    }
}
