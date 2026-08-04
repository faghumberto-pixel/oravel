<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Client;
use App\Models\HorimeterReading;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Link publico (sem login) de registro de horimetro -- funcionario do
 * cliente que alugou o equipamento, identificado por nome digitado (nao ha
 * User pra essa pessoa). Ver HourMeterPublicController.
 */
class HourMeterPublicTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Publico '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_horimeter_readings'],
        ]);

        return Tenant::create([
            'name' => 'Tenant Publico '.uniqid(), 'slug' => 'tenant-publico-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    private function makeLocadoAsset(Tenant $tenant): Asset
    {
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Locatario']);

        return Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Locado', 'tag' => 'AST-'.uniqid(),
            'status' => Asset::STATUS_LOCADO, 'client_id' => $client->id, 'horimetro_atual' => 0,
        ]);
    }

    public function test_public_page_shows_asset_when_token_is_valid_and_asset_is_locado(): void
    {
        $tenant = $this->makeTenant();
        $asset = $this->makeLocadoAsset($tenant);
        $token = $asset->hourMeterPublicToken();

        $response = $this->get("/hour-meter/publico/{$token}");

        $response->assertOk();
        $response->assertSee($asset->name);
        $response->assertSee('hourMeterPublic', false);
    }

    public function test_public_page_404s_for_invalid_token(): void
    {
        $response = $this->get('/hour-meter/publico/token-que-nao-existe');

        $response->assertNotFound();
    }

    public function test_public_page_404s_when_asset_is_not_locado(): void
    {
        $tenant = $this->makeTenant();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Disponivel', 'tag' => 'AST-'.uniqid(),
            'status' => Asset::STATUS_DISPONIVEL,
        ]);
        $token = $asset->hourMeterPublicToken();

        $response = $this->get("/hour-meter/publico/{$token}");

        $response->assertNotFound();
    }

    public function test_submits_a_reading_without_any_login(): void
    {
        Storage::fake('public');
        $tenant = $this->makeTenant();
        $asset = $this->makeLocadoAsset($tenant);
        $token = $asset->hourMeterPublicToken();

        $response = $this->post("/hour-meter/publico/{$token}", [
            'recorded_by_name' => 'João da Silva',
            'reading' => 88.5,
            'recorded_at' => now()->toIso8601String(),
            'photo' => UploadedFile::fake()->image('horimetro.jpg'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'synced');

        $reading = HorimeterReading::where('asset_id', $asset->id)->sole();
        $this->assertSame('88.50', $reading->reading);
        $this->assertSame('João da Silva', $reading->recorded_by_name);
        $this->assertNull($reading->recorded_by);
        $this->assertSame(HorimeterReading::SOURCE_PUBLIC_CLIENT, $reading->source);
        $this->assertTrue($reading->isPublicClientSource());
        $this->assertSame('Externo (Cliente Locatário)', $reading->originLabel());
        $this->assertSame('João da Silva', $reading->recordedByLabel());
        $this->assertNotNull($reading->photo_path);
        Storage::disk('public')->assertExists($reading->photo_path);

        $this->assertSame('88.50', $asset->fresh()->horimetro_atual);
    }

    public function test_requires_a_name(): void
    {
        $tenant = $this->makeTenant();
        $asset = $this->makeLocadoAsset($tenant);
        $token = $asset->hourMeterPublicToken();

        $response = $this->postJson("/hour-meter/publico/{$token}", [
            'reading' => 50,
            'recorded_at' => now()->toIso8601String(),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('recorded_by_name');
    }

    public function test_rejects_submission_for_invalid_token(): void
    {
        $response = $this->postJson('/hour-meter/publico/token-invalido', [
            'recorded_by_name' => 'Alguem',
            'reading' => 50,
            'recorded_at' => now()->toIso8601String(),
        ]);

        $response->assertNotFound();
    }

    public function test_rejects_submission_when_asset_is_no_longer_locado(): void
    {
        $tenant = $this->makeTenant();
        $asset = $this->makeLocadoAsset($tenant);
        $token = $asset->hourMeterPublicToken();
        $asset->update(['status' => Asset::STATUS_DISPONIVEL]);

        $response = $this->postJson("/hour-meter/publico/{$token}", [
            'recorded_by_name' => 'Alguem',
            'reading' => 50,
            'recorded_at' => now()->toIso8601String(),
        ]);

        $response->assertNotFound();
    }

    public function test_reset_without_confirmation_is_still_blocked_via_public_link(): void
    {
        $tenant = $this->makeTenant();
        $asset = $this->makeLocadoAsset($tenant);
        $token = $asset->hourMeterPublicToken();

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 500, 'recorded_at' => now()->subDay(),
            'source' => HorimeterReading::SOURCE_PUBLIC_CLIENT, 'recorded_by_name' => 'Primeiro',
        ]);

        $response = $this->postJson("/hour-meter/publico/{$token}", [
            'recorded_by_name' => 'Segundo',
            'reading' => 100,
            'recorded_at' => now()->toIso8601String(),
        ]);

        $response->assertUnprocessable();
        $this->assertSame(1, HorimeterReading::where('asset_id', $asset->id)->count());
    }
}
