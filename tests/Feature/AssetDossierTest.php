<?php

namespace Tests\Feature;

use App\Filament\Pages\AssetDossier;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ContractResource;
use App\Models\AbcMatrix;
use App\Models\AccountReceivable;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\EquipmentDamage;
use App\Models\EquipmentMovement;
use App\Models\HorimeterReading;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\ReportedProblem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetDossierTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Dossie '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_contracts', 'tabela_equipment_damages', 'tabela_maintenance_orders', 'tabela_abc_matrix'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Dossie '.uniqid(), 'slug' => 'tenant-dossie-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        // email_verified_at nao esta no $fillable de User -- create() acima
        // ignora silenciosamente, precisa de forceFill() (mesmo padrao de
        // TenantProvisioner::provision()). Sem isso, a rota do PDF
        // (middleware 'verified') redireciona pra /verify-email.
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function makeFullAsset(Tenant $tenant, User $technician): Asset
    {
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Dossiê']);

        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Guindaste Dossiê', 'tag' => 'AST-'.uniqid(),
            'patrimonio' => 'PAT-DOSSIE-001', 'status' => 'locado', 'client_id' => $client->id,
            'horimetro_atual' => 1234.5,
        ]);

        Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-DOSSIE-001', 'start_date' => now()->subMonths(2), 'status' => 'Ativo', 'price' => 5000,
        ]);

        AbcMatrix::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'nivel' => 'A', 'descricao' => 'Crítica']);

        $movement = EquipmentMovement::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'maintenance_order_id' => MaintenanceOrder::create([
                'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $technician->id,
                'description' => 'Movimentação inicial', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            ])->id,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
        ]);

        EquipmentDamage::create([
            'tenant_id' => $tenant->id, 'equipment_movement_id' => $movement->id,
            'maintenance_order_id' => $movement->maintenance_order_id, 'asset_id' => $asset->id,
            'reported_by_user_id' => $technician->id, 'severity' => EquipmentDamage::SEVERITY_MODERADA,
            'description' => 'Vazamento hidráulico na lança.',
        ]);

        $reportedProblem = ReportedProblem::create(['tenant_id' => $tenant->id, 'description' => 'Ruído anormal no motor']);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $technician->id,
            'description' => 'Verificar ruído', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Aberto', 'reported_problem_id' => $reportedProblem->id,
        ]);

        return $asset->fresh();
    }

    public function test_direct_link_shows_synthesized_dossier_data(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeFullAsset($tenant, $admin);

        $this->actingAs($admin);

        $response = $this->get(AssetDossier::getUrl(['assetId' => $asset->id]));

        $response->assertOk();
        $response->assertSee('Guindaste Dossiê');
        $response->assertSee('PAT-DOSSIE-001');
        $response->assertSee('Cliente Dossiê');
        $response->assertSee('CT-DOSSIE-001');
        $response->assertSee('Criticidade A');
        // Dossiê é minimalista (pedido 29/09/2026) -- não lista mais avaria/OS
        // por item, só contagem. Descrição/técnico de cada uma não aparecem.
        $response->assertDontSee('Vazamento hidráulico na lança.');
        $response->assertDontSee('Ruído anormal no motor');
        $response->assertSee('1 avaria(s) recente(s)');
        $response->assertSee('OS em aberto');
    }

    public function test_client_and_contract_are_clickable_links(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeFullAsset($tenant, $admin);
        $client = $asset->client;
        $contract = $asset->activeContract();

        $this->actingAs($admin);

        $response = $this->get(AssetDossier::getUrl(['assetId' => $asset->id]));

        $response->assertOk();
        $response->assertSee(ClientResource::getUrl('edit', ['record' => $client]), false);
        $response->assertSee(ContractResource::getUrl('edit', ['record' => $contract]), false);
    }

    public function test_worked_hours_are_calculated_from_contract_start_not_asset_lifetime(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeFullAsset($tenant, $admin);
        $contract = $asset->activeContract();

        // Leitura de antes do contrato -- não pode contar pro cálculo.
        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 100, 'recorded_at' => $contract->start_date->copy()->subDays(10),
            'source' => HorimeterReading::SOURCE_MANUAL,
        ]);
        // Leitura no início da locação (a que deve ser usada como base).
        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 350, 'recorded_at' => $contract->start_date->copy()->addDay(),
            'source' => HorimeterReading::SOURCE_MANUAL,
        ]);

        $asset->update(['horimetro_atual' => 590]); // 240h trabalhadas desde a locação

        $this->actingAs($admin);

        $page = Livewire::test(AssetDossier::class, ['assetId' => $asset->id]);
        $summary = $page->instance()->workedHoursSummary;

        $this->assertSame(350.0, $summary['horimetro_inicio_locacao']);
        $this->assertSame(240.0, $summary['horas_trabalhadas']);
        $this->assertNotNull($summary['dias_locado']);
        $this->assertEqualsWithDelta($summary['horas_trabalhadas'] / $summary['dias_locado'], $summary['media_diaria'], 0.01);
    }

    public function test_payment_status_shows_atrasado_when_client_has_overdue_receivable(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeFullAsset($tenant, $admin);
        $contract = $asset->activeContract();

        AccountReceivable::create([
            'tenant_id' => $tenant->id, 'client_id' => $contract->client_id, 'contract_id' => $contract->id,
            'description' => 'Fatura vencida', 'amount' => 500, 'due_date' => now()->subDays(5), 'status' => 'atrasado',
        ]);

        $this->actingAs($admin);

        $response = $this->get(AssetDossier::getUrl(['assetId' => $asset->id]));

        $response->assertOk();
        $response->assertSee('Cliente com pagamento em atraso');
    }

    public function test_payment_status_shows_em_dia_when_no_overdue_receivable(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeFullAsset($tenant, $admin);
        $contract = $asset->activeContract();

        AccountReceivable::create([
            'tenant_id' => $tenant->id, 'client_id' => $contract->client_id, 'contract_id' => $contract->id,
            'description' => 'Fatura paga', 'amount' => 500, 'due_date' => now()->subDays(5),
            'payment_date' => now()->subDays(6), 'status' => 'pago',
        ]);

        $this->actingAs($admin);

        $response = $this->get(AssetDossier::getUrl(['assetId' => $asset->id]));

        $response->assertOk();
        $response->assertSee('Cliente em dia com os pagamentos');
    }

    public function test_searching_by_exact_patrimonio_redirects_to_the_dossier(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeFullAsset($tenant, $admin);

        $this->actingAs($admin);

        Livewire::test(AssetDossier::class)
            ->set('query', 'PAT-DOSSIE-001')
            ->call('search')
            ->assertRedirect(AssetDossier::getUrl(['assetId' => $asset->id]));
    }

    /**
     * Busca flexivel: nao precisa do patrimonio exato, um trecho do nome
     * ja acha (sem "digitar errado" travar a busca).
     */
    public function test_searching_by_partial_name_redirects_to_the_dossier(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeFullAsset($tenant, $admin);

        $this->actingAs($admin);

        Livewire::test(AssetDossier::class)
            ->set('query', 'dossiê')
            ->call('search')
            ->assertRedirect(AssetDossier::getUrl(['assetId' => $asset->id]));
    }

    public function test_searching_with_multiple_matches_shows_a_picker_list_instead_of_redirecting(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $assetA = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste A', 'tag' => 'AST-A', 'patrimonio' => 'PAT-A', 'status' => 'disponivel']);
        $assetB = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste B', 'tag' => 'AST-B', 'patrimonio' => 'PAT-B', 'status' => 'disponivel']);

        $this->actingAs($admin);

        $component = Livewire::test(AssetDossier::class)
            ->set('query', 'Guindaste')
            ->call('search')
            ->assertNoRedirect();

        $ids = collect($component->get('searchResults'))->pluck('id');
        $this->assertTrue($ids->contains($assetA->id));
        $this->assertTrue($ids->contains($assetB->id));

        $component->call('selectResult', $assetA->id)
            ->assertRedirect(AssetDossier::getUrl(['assetId' => $assetA->id]));
    }

    public function test_searching_by_unknown_term_shows_notification_without_redirect(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test(AssetDossier::class)
            ->set('query', 'NAO-EXISTE-DE-JEITO-NENHUM')
            ->call('search')
            ->assertNoRedirect();
    }

    public function test_dossier_does_not_leak_another_tenants_asset(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        $assetA = $this->makeFullAsset($tenantA, $adminA);

        [$tenantB, $adminB] = $this->makeTenantAdmin();

        $this->actingAs($adminB);

        $response = $this->get(AssetDossier::getUrl(['assetId' => $assetA->id]));

        $response->assertOk();
        $response->assertDontSee('Guindaste Dossiê');
        $response->assertDontSee('PAT-DOSSIE-001');
    }

    public function test_pdf_download_returns_a_pdf_response(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeFullAsset($tenant, $admin);

        $this->actingAs($admin);

        $response = $this->get(route('assets.dossier.pdf', $asset));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
