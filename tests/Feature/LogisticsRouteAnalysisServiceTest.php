<?php

namespace Tests\Feature;

use App\Filament\Pages\OtimizacaoRotas;
use App\Models\AIAnalysis;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Depot;
use App\Models\EquipmentMovement;
use App\Models\FleetVehicle;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LogisticsRouteAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class LogisticsRouteAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Logistica IA '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_equipment_movements', 'tabela_fleet_vehicles', 'ia_diagnostico_avarias'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Logistica IA '.uniqid(), 'slug' => 'tenant-logistica-ia-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

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

    private function makeMovementWithGeolocatedClient(Tenant $tenant, FleetVehicle $vehicle, string $date, float $lat, float $lng, string $clientName): EquipmentMovement
    {
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => $clientName, 'latitude' => $lat, 'longitude' => $lng]);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste '.$clientName, 'client_id' => $client->id, 'status' => 'locado']);

        return EquipmentMovement::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $asset->id,
            'fleet_vehicle_id' => $vehicle->id,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
            'status' => EquipmentMovement::STATUS_AGUARDANDO_VISTORIA,
            'scheduled_at' => $date.' 08:00:00',
        ]);
    }

    public function test_analyze_date_returns_failure_without_calling_api_when_no_depot_has_coordinates(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        Http::fake();

        $analysis = app(LogisticsRouteAnalysisService::class)->analyzeDate($tenant->id, $admin->id, now()->toDateString());

        $this->assertSame(AIAnalysis::STATUS_FALHOU, $analysis->status);
        $this->assertStringContainsString('pátio', $analysis->error);
        Http::assertNothingSent();
    }

    public function test_analyze_date_returns_failure_without_calling_api_when_fewer_than_two_stops_are_geolocated(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        Depot::create([
            'tenant_id' => $tenant->id, 'name' => 'Pátio Central',
            'latitude' => -22.9099, 'longitude' => -47.0626, 'is_default' => true,
        ]);

        $vehicle = FleetVehicle::create(['tenant_id' => $tenant->id, 'placa' => 'AAA1111', 'modelo' => 'Truck', 'tipo' => 'truck']);
        $date = now()->toDateString();
        $this->makeMovementWithGeolocatedClient($tenant, $vehicle, $date, -22.90, -47.05, 'Cliente Único');

        Http::fake();

        $analysis = app(LogisticsRouteAnalysisService::class)->analyzeDate($tenant->id, $admin->id, $date);

        $this->assertSame(AIAnalysis::STATUS_FALHOU, $analysis->status);
        Http::assertNothingSent();
    }

    public function test_analyze_date_calculates_real_distances_and_stores_ai_summary_on_top(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        Depot::create([
            'tenant_id' => $tenant->id, 'name' => 'Pátio Central',
            'latitude' => -22.9099, 'longitude' => -47.0626, 'is_default' => true,
        ]);

        $vehicle = FleetVehicle::create([
            'tenant_id' => $tenant->id, 'placa' => 'BBB2222', 'modelo' => 'Truck', 'tipo' => 'truck',
            'custo_por_km' => 5,
        ]);

        $date = now()->toDateString();
        // Cliente longe agendado primeiro, cliente perto agendado depois --
        // sequencia "atual" (por scheduled_at) fica pior que a otimizada
        // (vizinho mais proximo), garantindo economia > 0 pra testar.
        $this->makeMovementWithGeolocatedClient($tenant, $vehicle, $date, -23.20, -47.30, 'Cliente Longe')
            ->update(['scheduled_at' => $date.' 08:00:00']);
        $this->makeMovementWithGeolocatedClient($tenant, $vehicle, $date, -22.95, -47.10, 'Cliente Perto')
            ->update(['scheduled_at' => $date.' 09:00:00']);

        $this->fakeClaudeJsonResponse([
            'resumo_geral' => 'Uma rota no dia, com economia real ao reordenar.',
            'recomendacoes' => ['Visitar o cliente mais próximo primeiro.'],
            'dica_pratica' => 'Sair mais cedo evita trânsito.',
        ]);

        $analysis = app(LogisticsRouteAnalysisService::class)->analyzeDate($tenant->id, $admin->id, $date);

        $this->assertSame(AIAnalysis::STATUS_CONCLUIDA, $analysis->status);
        $this->assertSame(\Illuminate\Support\Carbon::parse($date)->format('d/m/Y'), $analysis->reference_label);
        $this->assertSame('Uma rota no dia, com economia real ao reordenar.', $analysis->response['resumo_geral']);

        $rotas = $analysis->response['rotas'];
        $this->assertCount(1, $rotas);
        $this->assertSame('BBB2222', $rotas[0]['veiculo']);
        $this->assertGreaterThan(0, $rotas[0]['economia_km']);
        $this->assertNotNull($rotas[0]['economia_estimada_reais']);
    }

    public function test_otimizacao_rotas_page_action_creates_ai_analysis_of_type_logistica(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Depot::create([
            'tenant_id' => $tenant->id, 'name' => 'Pátio Central',
            'latitude' => -22.9099, 'longitude' => -47.0626, 'is_default' => true,
        ]);

        $vehicle = FleetVehicle::create(['tenant_id' => $tenant->id, 'placa' => 'CCC3333', 'modelo' => 'Truck', 'tipo' => 'truck']);
        $date = now()->toDateString();
        $this->makeMovementWithGeolocatedClient($tenant, $vehicle, $date, -22.95, -47.10, 'Cliente A');
        $this->makeMovementWithGeolocatedClient($tenant, $vehicle, $date, -23.05, -47.20, 'Cliente B');

        $this->fakeClaudeJsonResponse([
            'resumo_geral' => 'ok', 'recomendacoes' => [], 'dica_pratica' => null,
        ]);

        Livewire::test(OtimizacaoRotas::class)
            ->callAction('analisar', data: ['date' => $date])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('ai_analyses', [
            'tenant_id' => $tenant->id,
            'type' => AIAnalysis::TYPE_LOGISTICA,
            'status' => AIAnalysis::STATUS_CONCLUIDA,
        ]);
    }
}
