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
use Tests\TestCase;

/**
 * Gap do diagnostico de Geradores: o "combo" de ativos (gerador + cabo +
 * QTA) so' existia em SolicitacaoLocacao::assets() (fase comercial), sem
 * chegar no Contract final -- cada acessorio vira seu proprio Contract,
 * agrupados por solicitacao_locacao_id.
 */
class ContractSolicitacaoLocacaoComboTest extends TestCase
{
    use RefreshDatabase;

    public function test_contracts_from_the_same_combo_are_grouped_via_solicitacao_locacao(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Combo '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_contracts', 'tabela_solicitacao_locacao'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Combo '.uniqid(), 'slug' => 'tenant-combo-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Combo']);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Gerador']);
        $gerador = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador 150kVA', 'status' => Asset::STATUS_DISPONIVEL]);
        $qta = Asset::create(['tenant_id' => $tenant->id, 'name' => 'QTA 200A', 'status' => Asset::STATUS_DISPONIVEL]);

        $solicitacao = SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'customer_id' => $client->id,
            'category_id' => $category->id,
            'data_saida_prevista' => now()->addDays(3),
            'status_comercial' => 'proposta_em_andamento',
        ]);
        $solicitacao->assets()->attach([$gerador->id, $qta->id]);

        $contratoGerador = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $gerador->id,
            'solicitacao_locacao_id' => $solicitacao->id, 'contract_number' => 'CT-'.uniqid(),
            'start_date' => now(), 'price' => 1000,
        ]);
        $contratoQta = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $qta->id,
            'solicitacao_locacao_id' => $solicitacao->id, 'contract_number' => 'CT-'.uniqid(),
            'start_date' => now(), 'price' => 200,
        ]);

        $siblings = $solicitacao->fresh()->siblingContracts;

        $this->assertCount(2, $siblings);
        $this->assertTrue($siblings->contains('id', $contratoGerador->id));
        $this->assertTrue($siblings->contains('id', $contratoQta->id));
        $this->assertSame($solicitacao->id, $contratoGerador->fresh()->solicitacaoLocacao->id);
    }
}
