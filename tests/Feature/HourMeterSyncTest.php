<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\EquipmentHourMeter;
use App\Models\HorimeterReading;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * POST /api/v1/hour-meters/sync -- endpoint de sincronizacao em lote do app
 * mobile offline do tecnico (ver App\Http\Controllers\Api\V1\HourMeterSyncController).
 */
class HourMeterSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano HourMeter '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_horimeter_readings', 'tabela_equipment_hour_meters'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant HourMeter '.uniqid(), 'slug' => 'tenant-hourmeter-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Tecnico', 'email' => 'tecnico-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function makeAsset(Tenant $tenant): Asset
    {
        return Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Teste', 'tag' => 'AST-'.uniqid(),
            'status' => 'disponivel', 'horimetro_atual' => 0,
        ]);
    }

    public function test_syncs_a_batch_of_offline_readings_with_photo(): void
    {
        Storage::fake('public');
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin, 'sanctum');

        $clientUuid = (string) Str::uuid();

        $response = $this->post('/api/v1/hour-meters/sync', [
            'readings' => [
                [
                    'client_uuid' => $clientUuid,
                    'asset_id' => $asset->id,
                    'reading' => 123.5,
                    'recorded_at' => now()->toIso8601String(),
                    'latitude' => -23.55052,
                    'longitude' => -46.633308,
                    'photo' => UploadedFile::fake()->image('horimetro.jpg'),
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('synced', 1);
        $response->assertJsonPath('failed', 0);
        $response->assertJsonPath('results.0.client_uuid', $clientUuid);
        $response->assertJsonPath('results.0.status', 'synced');

        $record = EquipmentHourMeter::where('client_uuid', $clientUuid)->sole();
        $this->assertSame(EquipmentHourMeter::STATUS_SYNCED, $record->sync_status);
        $this->assertNotNull($record->photo_path);
        Storage::disk('public')->assertExists($record->photo_path);

        $reading = HorimeterReading::where('asset_id', $asset->id)->sole();
        $this->assertSame('123.50', $reading->reading);
        $this->assertSame($record->horimeter_reading_id, $reading->id);

        $this->assertSame('123.50', $asset->fresh()->horimetro_atual);
    }

    public function test_resyncing_the_same_client_uuid_is_idempotent(): void
    {
        Storage::fake('public');
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin, 'sanctum');

        $clientUuid = (string) Str::uuid();
        $payload = [
            'readings' => [[
                'client_uuid' => $clientUuid,
                'asset_id' => $asset->id,
                'reading' => 50,
                'recorded_at' => now()->toIso8601String(),
            ]],
        ];

        $this->post('/api/v1/hour-meters/sync', $payload)->assertOk();
        $this->post('/api/v1/hour-meters/sync', $payload)->assertOk();

        $this->assertSame(1, EquipmentHourMeter::where('client_uuid', $clientUuid)->count());
        $this->assertSame(1, HorimeterReading::where('asset_id', $asset->id)->count());
    }

    public function test_a_reset_without_confirmation_fails_this_item_but_not_the_whole_batch(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin, 'sanctum');

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 500, 'recorded_at' => now()->subDay(), 'recorded_by' => $admin->id,
        ]);

        $badUuid = (string) Str::uuid();
        $goodUuid = (string) Str::uuid();

        $response = $this->post('/api/v1/hour-meters/sync', [
            'readings' => [
                [
                    'client_uuid' => $badUuid,
                    'asset_id' => $asset->id,
                    'reading' => 100,
                    'recorded_at' => now()->toIso8601String(),
                ],
                [
                    'client_uuid' => $goodUuid,
                    'asset_id' => $asset->id,
                    'reading' => 600,
                    'recorded_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('synced', 1);
        $response->assertJsonPath('failed', 1);

        $failed = EquipmentHourMeter::where('client_uuid', $badUuid)->sole();
        $this->assertSame(EquipmentHourMeter::STATUS_FAILED, $failed->sync_status);
        $this->assertNotNull($failed->sync_error);

        $synced = EquipmentHourMeter::where('client_uuid', $goodUuid)->sole();
        $this->assertSame(EquipmentHourMeter::STATUS_SYNCED, $synced->sync_status);

        // A leitura invalida nao deixou HorimeterReading orfao pra tras.
        $this->assertSame(2, HorimeterReading::where('asset_id', $asset->id)->count());
    }

    public function test_rejects_reading_for_an_asset_from_another_tenant(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$otherTenant] = $this->makeTenantAdmin();
        $foreignAsset = $this->makeAsset($otherTenant);
        $this->actingAs($admin, 'sanctum');

        $response = $this->post('/api/v1/hour-meters/sync', [
            'readings' => [[
                'client_uuid' => (string) Str::uuid(),
                'asset_id' => $foreignAsset->id,
                'reading' => 10,
                'recorded_at' => now()->toIso8601String(),
            ]],
        ]);

        $response->assertOk();
        $response->assertJsonPath('failed', 1);
        $response->assertJsonPath('synced', 0);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/hour-meters/sync', ['readings' => []]);

        $response->assertUnauthorized();
    }

    /**
     * Prova que a autenticacao real (cookie de sessao web, nao o
     * actingAs(..., 'sanctum') simulado usado nos outros testes desta
     * classe) efetivamente funciona -- EnsureFrontendRequestsAreStateful
     * precisa estar ativo no grupo 'api' do Kernel pra isso passar. E' a
     * mesma forma que a tela mobile (Blade+Alpine, mesmo dominio, mesma
     * sessao do login web) vai chamar a API via fetch().
     */
    public function test_authenticates_via_web_session_cookie_like_the_mobile_page_does(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);

        $this->actingAs($admin);

        $response = $this->post('/api/v1/hour-meters/sync', [
            'readings' => [[
                'client_uuid' => (string) Str::uuid(),
                'asset_id' => $asset->id,
                'reading' => 42,
                'recorded_at' => now()->toIso8601String(),
            ]],
        ]);

        $response->assertOk();
        $response->assertJsonPath('synced', 1);
    }
}
