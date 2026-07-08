<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SolicitacaoLocacao;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SolicitacaoLocacaoRulesTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Locacao '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_solicitacao_locacao'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Locacao '.uniqid(), 'slug' => 'tenant-locacao-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_request_without_contract_requires_a_reservation_deadline(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Potencial']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Guindastes']);

        $this->expectException(ValidationException::class);

        SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'customer_id' => $client->id,
            'category_id' => $category->id,
            'status_comercial' => 'proposta_em_andamento',
        ]);
    }

    public function test_request_with_contract_does_not_require_a_deadline(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Com Contrato']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Guindastes']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste Contratado', 'status' => Asset::STATUS_LOCADO]);
        $contract = Contract::create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id, 'contract_number' => 'CT-001', 'start_date' => now()]);

        $solicitacao = SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'customer_id' => $client->id,
            'contract_id' => $contract->id,
            'category_id' => $category->id,
            'status_comercial' => 'proposta_em_andamento',
        ]);

        $this->assertNotNull($solicitacao->id);
    }

    public function test_cannot_close_contract_while_asset_is_unavailable(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Guindastes']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste em Manutenção', 'status' => Asset::STATUS_MANUTENCAO]);

        $solicitacao = SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'customer_id' => $client->id,
            'category_id' => $category->id,
            'asset_id' => $asset->id,
            'data_saida_prevista' => now()->addWeek(),
            'status_comercial' => 'proposta_em_andamento',
        ]);

        $this->expectException(ValidationException::class);

        $solicitacao->update(['status_comercial' => 'contrato_fechado']);
    }

    public function test_can_close_contract_once_asset_is_available(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Guindastes']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste Disponível', 'status' => Asset::STATUS_DISPONIVEL]);

        $solicitacao = SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'customer_id' => $client->id,
            'category_id' => $category->id,
            'asset_id' => $asset->id,
            'data_saida_prevista' => now()->addWeek(),
            'status_comercial' => 'proposta_em_andamento',
        ]);

        $solicitacao->update(['status_comercial' => 'contrato_fechado']);

        $this->assertSame('contrato_fechado', $solicitacao->fresh()->status_comercial);
    }
}
