<?php

namespace Tests\Feature;

use App\Filament\Resources\HorimeterReadingResource\Pages\ListHorimeterReadings;
use App\Models\Asset;
use App\Models\Client;
use App\Models\HorimeterReading;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tela desktop de monitoramento geral de horímetro (Filament, so leitura) --
 * reune apontamentos de qualquer origem (mobile offline, manual, O.S.,
 * checklist) numa unica listagem com filtros. Ver HorimeterReadingResource.
 */
class HorimeterReadingResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Monitor '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_horimeter_readings'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Monitor '.uniqid(), 'slug' => 'tenant-monitor-'.uniqid(),
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

    public function test_lists_readings_from_every_source(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Monitor']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Monitor', 'tag' => 'AST-'.uniqid(),
            'status' => 'disponivel', 'client_id' => $client->id,
        ]);
        $this->actingAs($admin);

        $mobile = HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'reading' => 100,
            'recorded_at' => now()->subHour(), 'recorded_by' => $admin->id,
            'source' => HorimeterReading::SOURCE_MOBILE_SYNC,
        ]);
        $manual = HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'reading' => 150,
            'recorded_at' => now(), 'recorded_by' => $admin->id,
            'source' => HorimeterReading::SOURCE_MANUAL, 'reset_confirmed' => true,
        ]);

        Livewire::test(ListHorimeterReadings::class)
            ->assertCanSeeTableRecords([$mobile, $manual]);
    }

    public function test_filters_by_source_origin(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Origem', 'tag' => 'AST-'.uniqid(),
            'status' => 'disponivel',
        ]);
        $this->actingAs($admin);

        $mobile = HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'reading' => 200,
            'recorded_at' => now(), 'recorded_by' => $admin->id,
            'source' => HorimeterReading::SOURCE_MOBILE_SYNC,
        ]);
        $manual = HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'reading' => 250,
            'recorded_at' => now()->addMinute(), 'recorded_by' => $admin->id,
            'source' => HorimeterReading::SOURCE_MANUAL, 'reset_confirmed' => true,
        ]);

        Livewire::test(ListHorimeterReadings::class)
            ->filterTable('source', HorimeterReading::SOURCE_MOBILE_SYNC)
            ->assertCanSeeTableRecords([$mobile])
            ->assertCanNotSeeTableRecords([$manual]);
    }

    public function test_filters_by_technician(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Tecnico', 'tag' => 'AST-'.uniqid(),
            'status' => 'disponivel',
        ]);
        $outroTecnico = User::create([
            'name' => 'Outro Tecnico', 'email' => 'outro-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $this->actingAs($admin);

        $doAdmin = HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'reading' => 300,
            'recorded_at' => now(), 'recorded_by' => $admin->id,
        ]);
        $doOutro = HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'reading' => 350,
            'recorded_at' => now()->addMinute(), 'recorded_by' => $outroTecnico->id, 'reset_confirmed' => true,
        ]);

        Livewire::test(ListHorimeterReadings::class)
            ->filterTable('recorded_by', $admin->id)
            ->assertCanSeeTableRecords([$doAdmin])
            ->assertCanNotSeeTableRecords([$doOutro]);
    }

    public function test_shows_public_client_source_with_typed_name_and_no_user(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Locatario Relatorio']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Publico', 'tag' => 'AST-'.uniqid(),
            'status' => 'locado', 'client_id' => $client->id,
        ]);
        $this->actingAs($admin);

        $publicReading = HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'reading' => 321,
            'recorded_at' => now(), 'recorded_by' => null, 'recorded_by_name' => 'Funcionario do Cliente',
            'source' => HorimeterReading::SOURCE_PUBLIC_CLIENT,
        ]);

        $this->assertSame('Externo (Cliente Locatário)', $publicReading->originLabel());
        $this->assertSame('Funcionario do Cliente', $publicReading->recordedByLabel());

        Livewire::test(ListHorimeterReadings::class)
            ->assertCanSeeTableRecords([$publicReading])
            ->assertSee('Funcionario do Cliente');
    }

    public function test_filters_by_client(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $clientA = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente A']);
        $clientB = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente B']);
        $assetA = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Cliente A', 'tag' => 'AST-'.uniqid(),
            'status' => 'disponivel', 'client_id' => $clientA->id,
        ]);
        $assetB = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Cliente B', 'tag' => 'AST-'.uniqid(),
            'status' => 'disponivel', 'client_id' => $clientB->id,
        ]);
        $this->actingAs($admin);

        $readingA = HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetA->id, 'reading' => 400,
            'recorded_at' => now(), 'recorded_by' => $admin->id,
        ]);
        $readingB = HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetB->id, 'reading' => 450,
            'recorded_at' => now()->addMinute(), 'recorded_by' => $admin->id, 'reset_confirmed' => true,
        ]);

        Livewire::test(ListHorimeterReadings::class)
            ->filterTable('client', $clientA->id)
            ->assertCanSeeTableRecords([$readingA])
            ->assertCanNotSeeTableRecords([$readingB]);
    }

    public function test_filters_by_asset(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $assetA = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Filtro A', 'tag' => 'AST-'.uniqid(),
            'status' => 'disponivel',
        ]);
        $assetB = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Filtro B', 'tag' => 'AST-'.uniqid(),
            'status' => 'disponivel',
        ]);
        $this->actingAs($admin);

        $readingA = HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetA->id, 'reading' => 500,
            'recorded_at' => now(), 'recorded_by' => $admin->id,
        ]);
        $readingB = HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetB->id, 'reading' => 550,
            'recorded_at' => now()->addMinute(), 'recorded_by' => $admin->id, 'reset_confirmed' => true,
        ]);

        Livewire::test(ListHorimeterReadings::class)
            ->filterTable('asset_id', $assetA->id)
            ->assertCanSeeTableRecords([$readingA])
            ->assertCanNotSeeTableRecords([$readingB]);
    }

    public function test_does_not_leak_readings_from_another_tenant(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$otherTenant] = $this->makeTenantAdmin();

        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Meu', 'tag' => 'AST-'.uniqid(),
            'status' => 'disponivel',
        ]);
        $foreignAsset = Asset::create([
            'tenant_id' => $otherTenant->id, 'name' => 'Gerador Alheio', 'tag' => 'AST-'.uniqid(),
            'status' => 'disponivel',
        ]);
        $this->actingAs($admin);

        $mine = HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'reading' => 10,
            'recorded_at' => now(), 'recorded_by' => $admin->id,
        ]);
        $foreign = HorimeterReading::create([
            'tenant_id' => $otherTenant->id, 'asset_id' => $foreignAsset->id, 'reading' => 20,
            'recorded_at' => now(), 'recorded_by' => null,
        ]);

        Livewire::test(ListHorimeterReadings::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$foreign]);
    }
}
