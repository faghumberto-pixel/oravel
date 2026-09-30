<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceOrderResource\Pages\CreateMaintenanceOrder;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\MaintenanceOrder;
use App\Models\Material;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OsPhotoOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Teste pedido pelo usuário 29/09/2026: preencher a OS a partir da foto de
 * uma OS em papel de terceiros (layout variável), via IA com visão
 * (App\Services\OsPhotoOcrService, usado em
 * App\Filament\Resources\MaintenanceOrderResource::preencherViaFotoIA()).
 */
class OsPhotoOcrServiceTest extends TestCase
{
    use RefreshDatabase;

    private const FAKE_PHOTO = 'data:image/jpeg;base64,ZmFrZS1waG90by1ieXRlcw==';

    private function fakeClaudeJsonResponse(array $payload): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => json_encode($payload)],
                ],
            ], 200),
        ]);
    }

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano OS Foto '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_assets', 'tabela_clients'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant OS Foto '.uniqid(), 'slug' => 'tenant-os-foto-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    public function test_extract_parses_structured_data_from_a_valid_photo(): void
    {
        $this->fakeClaudeJsonResponse([
            'maintenance_type' => 'Corretiva',
            'description' => 'Vazamento hidráulico na lança.',
            'started_at' => '2026-09-20',
            'finished_at' => '2026-09-21',
            'labor_cost' => 300.0,
            'material_cost' => 150.0,
            'has_client_signature' => true,
            'checklist_notes' => 'Nível de óleo OK.',
            'asset_text' => 'Escavadeira CAT 320',
            'client_text' => 'Rio Verde',
            'technician_text' => 'Carlos',
        ]);

        $result = app(OsPhotoOcrService::class)->extract(self::FAKE_PHOTO);

        $this->assertTrue($result['ok']);
        $this->assertSame('Corretiva', $result['data']['maintenance_type']);
        $this->assertSame('Vazamento hidráulico na lança.', $result['data']['description']);
        $this->assertSame('Escavadeira CAT 320', $result['data']['asset_text']);
    }

    public function test_extract_fails_gracefully_with_an_invalid_data_url(): void
    {
        $result = app(OsPhotoOcrService::class)->extract('not-a-data-url');

        $this->assertFalse($result['ok']);
        $this->assertNull($result['data']);
        $this->assertNotNull($result['error']);
    }

    public function test_extract_fails_gracefully_when_ai_response_is_not_valid_json(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'Não consigo ler essa foto direito.'],
                ],
            ], 200),
        ]);

        $result = app(OsPhotoOcrService::class)->extract(self::FAKE_PHOTO);

        $this->assertFalse($result['ok']);
        $this->assertNotNull($result['error']);
    }

    public function test_maintenance_order_create_form_renders_with_photo_import_section(): void
    {
        [, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test(CreateMaintenanceOrder::class)
            ->assertFormFieldExists('photo_import')
            ->assertSuccessful();
    }

    public function test_resolve_fields_matches_asset_client_and_technician_by_partial_text(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Terraplenagem']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'asset_category_id' => $category->id,
            'name' => 'Escavadeira Hidráulica CAT 320', 'last_horimetro' => 1284.50,
            'status' => Asset::STATUS_DISPONIVEL,
        ]);
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Obras Rio Verde Ltda']);

        $data = [
            'maintenance_type' => 'Corretiva',
            'description' => 'Vazamento hidráulico na lança.',
            'started_at' => '2026-09-20',
            'finished_at' => '2026-09-21',
            'labor_cost' => 300,
            'material_cost' => 150,
            'has_client_signature' => true,
            'checklist_notes' => 'Nível de óleo OK.',
            'asset_text' => 'Escavadeira CAT 320',
            'client_text' => 'Rio Verde',
            'technician_text' => $admin->name,
        ];

        $technicianOptions = [$admin->id => "{$admin->name} (livre)"];

        $resolved = app(OsPhotoOcrService::class)->resolveFields($data, $tenant->id, $technicianOptions);

        $this->assertSame($asset->id, $resolved['fields']['asset_id']);
        $this->assertSame(1284.50, (float) $resolved['fields']['horimetro_anterior']);
        $this->assertSame($client->id, $resolved['fields']['client_id']);
        $this->assertSame($admin->id, $resolved['fields']['technician_id']);
        $this->assertSame(MaintenanceOrder::TYPE_CORRECTIVE, $resolved['fields']['maintenance_type']);
        $this->assertSame('Vazamento hidráulico na lança.', $resolved['fields']['description']);
        $this->assertSame(300, $resolved['fields']['labor_cost']);
        $this->assertEmpty($resolved['notFound']);
    }

    public function test_resolve_fields_reports_not_found_when_text_does_not_match_any_record(): void
    {
        [$tenant] = $this->makeTenantAdmin();

        $data = [
            'asset_text' => 'Máquina Que Não Existe',
            'client_text' => 'Cliente Fantasma Ltda',
            'technician_text' => 'Ninguém Com Esse Nome',
        ];

        $resolved = app(OsPhotoOcrService::class)->resolveFields($data, $tenant->id, []);

        $this->assertArrayNotHasKey('asset_id', $resolved['fields']);
        $this->assertArrayNotHasKey('client_id', $resolved['fields']);
        $this->assertArrayNotHasKey('technician_id', $resolved['fields']);
        $this->assertCount(3, $resolved['notFound']);
    }

    public function test_resolve_fields_rejects_malformed_or_impossible_dates(): void
    {
        $resolved = app(OsPhotoOcrService::class)->resolveFields([
            'started_at' => '2026-09-28',
            'finished_at' => '30/09/2026',
        ], null, []);

        $this->assertSame('2026-09-28', $resolved['fields']['started_at']);
        $this->assertArrayNotHasKey('finished_at', $resolved['fields']);
        $this->assertSame(['Data (lida: "30/09/2026")'], $resolved['notFound']);

        $impossible = app(OsPhotoOcrService::class)->resolveFields(['started_at' => '2026-02-31'], null, []);

        $this->assertArrayNotHasKey('started_at', $impossible['fields']);
        $this->assertCount(1, $impossible['notFound']);
    }

    public function test_resolve_fields_turns_parts_table_into_material_lines(): void
    {
        [$tenant] = $this->makeTenantAdmin();

        $material = Material::create(['tenant_id' => $tenant->id, 'sku' => 'FLT-1', 'name' => 'Filtro de Óleo Hidráulico', 'unit_cost' => 90]);
        Material::create(['tenant_id' => $tenant->id, 'sku' => 'ROL-1', 'name' => 'Rolamento Especial', 'unit_cost' => 50, 'requires_serial_number' => true]);

        $resolved = app(OsPhotoOcrService::class)->resolveFields(['parts' => [
            ['description' => 'Filtro óleo hidráulico', 'quantity' => 2, 'unit_price' => 180],
            ['description' => 'Rolamento especial', 'quantity' => 1, 'unit_price' => 220],
            ['description' => 'Óleo 15W40', 'quantity' => '40', 'unit_price' => null],
            ['description' => '  ', 'quantity' => 1],
        ]], $tenant->id, []);

        $lines = array_values($resolved['fields']['materials']);

        $this->assertCount(3, $lines);
        $this->assertSame($material->id, $lines[0]['material_id']);
        $this->assertNull($lines[0]['name']);
        $this->assertEquals(180, $lines[0]['unit_price']);
        $this->assertNull($lines[1]['material_id']);
        $this->assertSame('Rolamento especial', $lines[1]['name']);
        $this->assertNull($lines[2]['material_id']);
        $this->assertSame('Óleo 15W40', $lines[2]['name']);
        $this->assertEquals(40, $lines[2]['quantity']);
    }
}
