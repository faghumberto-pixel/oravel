<?php

namespace Tests\Feature;

use App\Filament\Client\Pages\AtualizarHorimetro;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\HorimeterReading;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Portal do Cliente Fase 2 (2026-08-26): Client atualiza horímetro do
 * próprio ativo locado, reaproveitando HourMeterReadingWriter. Cobre a
 * trava de negócio (só ativo LOCADO) e a revalidação servidor-side de
 * que o asset_id pertence de fato ao Client autenticado.
 */
class ClientPortalHourMeterTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithClientAndAsset(string $assetStatus = Asset::STATUS_LOCADO): array
    {
        $plan = Plan::create([
            'name' => 'Plano Horimetro '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_clients', 'tabela_contracts', 'tabela_horimeter_readings'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Horimetro '.uniqid(), 'slug' => 'tenant-horimetro-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente Horimetro',
            'email' => 'horimetro-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Horimetro', 'status' => $assetStatus]);

        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-HOR-'.uniqid(), 'start_date' => now(),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 1000,
        ]);

        return [$tenant, $client, $asset, $contract];
    }

    public function test_client_can_record_hour_meter_reading_for_own_locado_asset(): void
    {
        [$tenant, $client, $asset] = $this->makeTenantWithClientAndAsset();

        $this->actingAs($client, 'client');

        Livewire::test(AtualizarHorimetro::class)
            ->fillForm([
                'asset_id' => $asset->id,
                'reading' => 1234.5,
                'recorded_at' => now(),
            ])
            ->call('create');

        $reading = HorimeterReading::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('asset_id', $asset->id)
            ->first();

        $this->assertNotNull($reading);
        $this->assertEquals(1234.5, $reading->reading);
        $this->assertSame(HorimeterReading::SOURCE_CLIENT_PORTAL, $reading->source);
        $this->assertSame($client->name, $reading->recorded_by_name);
        $this->assertNull($reading->recorded_by);
    }

    public function test_client_cannot_record_reading_for_asset_not_locado(): void
    {
        // ContractObserver::created() sempre marca o Asset como 'locado' ao
        // vincular um Contract -- pra testar a trava, muda o status DEPOIS
        // (ex: ativo devolvido, contrato ainda não atualizado no sistema).
        [$tenant, $client, $asset] = $this->makeTenantWithClientAndAsset();
        $asset->update(['status' => Asset::STATUS_DISPONIVEL]);

        $this->actingAs($client, 'client');

        Livewire::test(AtualizarHorimetro::class)
            ->fillForm([
                'asset_id' => $asset->id,
                'reading' => 500,
                'recorded_at' => now(),
            ])
            ->call('create');

        $count = HorimeterReading::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('asset_id', $asset->id)
            ->count();

        $this->assertSame(0, $count);
    }

    public function test_client_cannot_record_reading_for_asset_belonging_to_another_client(): void
    {
        [$tenant, $client] = $this->makeTenantWithClientAndAsset();

        $otherAsset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador de Outro Cliente', 'status' => Asset::STATUS_LOCADO]);
        $otherClient = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Outro Cliente',
            'email' => 'outro-horimetro-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);
        Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $otherClient->id, 'asset_id' => $otherAsset->id,
            'contract_number' => 'CT-OUTRO-'.uniqid(), 'start_date' => now(),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 1000,
        ]);

        $this->actingAs($client, 'client');

        Livewire::test(AtualizarHorimetro::class)
            ->fillForm([
                'asset_id' => $otherAsset->id,
                'reading' => 999,
                'recorded_at' => now(),
            ])
            ->call('create');

        $count = HorimeterReading::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('asset_id', $otherAsset->id)
            ->count();

        $this->assertSame(0, $count);
    }
}
