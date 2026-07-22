<?php

namespace Tests\Feature;

use App\Filament\Pages\ApontamentoHorimetro;
use App\Models\Asset;
use App\Models\HorimeterReading;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Item 1 do pedido de 2026-07-22 (3 módulos de frota): apontamento manual
 * de horímetro, com trilha de auditoria (HorimeterReading) por cima da
 * coluna legada Asset.horimetro_atual -- ver HorimeterReadingObserver pra
 * detecção de reset/salto, e MaintenanceOrder::booted() pro apontamento
 * automático quando uma O.S. preenche horimetro_entry.
 */
class HorimeterReadingTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Horimetro '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_horimeter_readings', 'tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Horimetro '.uniqid(), 'slug' => 'tenant-horimetro-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function makeAsset(Tenant $tenant, float $horimetroAtual = 0): Asset
    {
        return Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Teste', 'tag' => 'AST-'.uniqid(),
            'status' => 'disponivel', 'horimetro_atual' => $horimetroAtual,
        ]);
    }

    public function test_reading_lower_than_last_requires_reset_confirmation(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 500, 'recorded_at' => now()->subDay(), 'recorded_by' => $admin->id,
        ]);

        $this->expectException(ValidationException::class);

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 100, 'recorded_at' => now(), 'recorded_by' => $admin->id,
        ]);
    }

    public function test_reading_lower_than_last_succeeds_when_reset_confirmed(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 500, 'recorded_at' => now()->subDay(), 'recorded_by' => $admin->id,
        ]);

        $novo = HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 100, 'recorded_at' => now(), 'recorded_by' => $admin->id,
            'reset_confirmed' => true,
        ]);

        $this->assertSame('100.00', $novo->reading);
    }

    public function test_jump_above_threshold_requires_confirmation(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 100, 'recorded_at' => now()->subDay(), 'recorded_by' => $admin->id,
        ]);

        config(['oravel.horimeter_jump_threshold' => 500]);

        $this->expectException(ValidationException::class);

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 700, 'recorded_at' => now(), 'recorded_by' => $admin->id,
        ]);
    }

    public function test_normal_incremental_reading_does_not_require_confirmation(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 100, 'recorded_at' => now()->subDay(), 'recorded_by' => $admin->id,
        ]);

        $novo = HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 150, 'recorded_at' => now(), 'recorded_by' => $admin->id,
        ]);

        $this->assertSame('150.00', $novo->reading);
    }

    public function test_creating_a_reading_syncs_asset_horimetro_atual_and_never_regresses(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant, horimetroAtual: 200);
        $this->actingAs($admin);

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 300, 'recorded_at' => now(), 'recorded_by' => $admin->id,
        ]);

        $this->assertSame('300.00', $asset->fresh()->horimetro_atual);

        // Reset confirmado grava o apontamento, mas NAO regride a coluna
        // legada (mesmo criterio ja usado por MaintenanceOrder/PreventiveMaintenanceExecution).
        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 50, 'recorded_at' => now(), 'recorded_by' => $admin->id,
            'reset_confirmed' => true,
        ]);

        $this->assertSame('300.00', $asset->fresh()->horimetro_atual);
    }

    public function test_current_horimeter_accessor_is_cached_for_5_minutes(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 100, 'recorded_at' => now(), 'recorded_by' => $admin->id,
        ]);

        $this->assertSame(100.0, $asset->fresh()->current_horimeter);

        // Escreve direto no banco por fora do Observer (nao invalida cache) --
        // o accessor deve continuar devolvendo o valor antigo em cache.
        HorimeterReading::withoutEvents(function () use ($tenant, $asset, $admin) {
            HorimeterReading::create([
                'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
                'reading' => 999, 'recorded_at' => now()->addMinute(), 'recorded_by' => $admin->id,
            ]);
        });

        $this->assertSame(100.0, $asset->fresh()->current_horimeter);

        Cache::forget("horimeter:current:{$tenant->id}:{$asset->id}");
        $this->assertSame(999.0, $asset->fresh()->current_horimeter);
    }

    public function test_creating_a_reading_invalidates_the_cache(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 100, 'recorded_at' => now(), 'recorded_by' => $admin->id,
        ]);
        $this->assertSame(100.0, $asset->fresh()->current_horimeter);

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 200, 'recorded_at' => now()->addMinute(), 'recorded_by' => $admin->id,
        ]);

        $this->assertSame(200.0, $asset->fresh()->current_horimeter);
    }

    public function test_saving_a_maintenance_order_with_horimetro_entry_creates_a_reading(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Visita', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'horimetro_entry' => 450,
        ]);

        $reading = HorimeterReading::where('asset_id', $asset->id)->sole();
        $this->assertSame('450.00', $reading->reading);
        $this->assertSame(HorimeterReading::SOURCE_MAINTENANCE_ORDER, $reading->source);
        $this->assertTrue($reading->reset_confirmed);

        // Salvar de novo sem mudar horimetro_entry nao deve empilhar outro apontamento.
        $order->update(['description' => 'Visita atualizada']);
        $this->assertSame(1, HorimeterReading::where('asset_id', $asset->id)->count());

        // Mudar o valor de verdade cria um apontamento novo.
        $order->update(['horimetro_entry' => 470]);
        $this->assertSame(2, HorimeterReading::where('asset_id', $asset->id)->count());
    }

    public function test_apontamento_horimetro_page_creates_a_reading(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        Livewire::test(ApontamentoHorimetro::class)
            ->fillForm(['asset_id' => $asset->id, 'reading' => 250])
            ->call('save');

        $reading = HorimeterReading::where('asset_id', $asset->id)->sole();
        $this->assertSame('250.00', $reading->reading);
        $this->assertSame(HorimeterReading::SOURCE_MANUAL, $reading->source);
        $this->assertSame($admin->id, $reading->recorded_by);
    }

    public function test_apontamento_horimetro_page_shows_friendly_error_instead_of_crashing(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 500, 'recorded_at' => now(), 'recorded_by' => $admin->id,
        ]);

        Livewire::test(ApontamentoHorimetro::class)
            ->fillForm(['asset_id' => $asset->id, 'reading' => 100])
            ->call('save')
            ->assertHasNoErrors();

        // Nao criou o apontamento invalido (bloqueado no model, capturado
        // pela pagina como notificacao, nao como excecao estourada).
        $this->assertSame(1, HorimeterReading::where('asset_id', $asset->id)->count());
    }
}
