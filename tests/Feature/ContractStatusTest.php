<?php

namespace Tests\Feature;

use App\Filament\Resources\ContractResource\Pages\CreateContract;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O formulario de contrato nao tinha campo de situacao: todo contrato criado
 * pela tela ficava em Rascunho para sempre (nao faturava, nao contava como
 * contrato ativo do ativo e nao havia como encerrar). Achado na conferencia
 * da aula de Contratos (14/10/2026).
 */
class ContractStatusTest extends TestCase
{
    use DatabaseTransactions;

    private function setUpTenant(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Contrato '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_contracts', 'tabela_assets', 'tabela_clients'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Contrato '.uniqid(), 'slug' => 'tenant-contrato-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $user = User::create([
            'name' => 'Comercial', 'email' => 'ct-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $role = new Role(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $role->save();
        $user->assignRole($role);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Contrato']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador', 'tag' => 'AST-'.uniqid(),
            'patrimonio' => 'PAT-'.uniqid(), 'status' => 'disponivel',
        ]);

        return [$tenant, $user, $client, $asset];
    }

    private function contract(Tenant $tenant, Client $client, Asset $asset, array $extra = []): Contract
    {
        return Contract::create(array_merge([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => now()->toDateString(),
            'price' => 1000, 'billing_type' => Contract::BILLING_MENSAL_FIXO,
            'local_tipo' => Contract::LOCAL_TIPO_ENDERECO_CLIENTE, 'status' => 'Ativo',
        ], $extra));
    }

    public function test_status_options_are_rascunho_ativo_encerrado(): void
    {
        $this->assertSame(['Draft', 'Ativo', 'Encerrado'], array_keys(Contract::statusOptions()));
    }

    public function test_form_has_status_field_defaulting_to_ativo(): void
    {
        [$tenant, $user] = $this->setUpTenant();
        $this->actingAs($user);

        Livewire::test(CreateContract::class)
            ->assertFormFieldExists('status')
            ->assertFormSet(['status' => 'Ativo']);
    }

    public function test_closing_the_contract_frees_the_asset(): void
    {
        [$tenant, $user, $client, $asset] = $this->setUpTenant();
        $contract = $this->contract($tenant, $client, $asset);
        $this->assertSame('locado', $asset->fresh()->status);

        $contract->update(['status' => 'Encerrado']);

        $this->assertFalse((bool) $contract->fresh()->is_active);
        $this->assertSame('disponivel', $asset->fresh()->status);
        $this->assertNull($asset->fresh()->client_id);
    }

    public function test_reopening_a_closed_contract_locks_the_asset_again(): void
    {
        [$tenant, $user, $client, $asset] = $this->setUpTenant();
        $contract = $this->contract($tenant, $client, $asset);
        $contract->update(['status' => 'Encerrado']);

        $contract->update(['status' => 'Ativo']);

        $this->assertTrue((bool) $contract->fresh()->is_active);
        $this->assertSame('locado', $asset->fresh()->status);
        $this->assertSame($client->id, $asset->fresh()->client_id);
    }

    public function test_moving_a_draft_to_ativo_makes_it_the_assets_active_contract(): void
    {
        [$tenant, $user, $client, $asset] = $this->setUpTenant();
        $contract = $this->contract($tenant, $client, $asset, ['status' => 'Draft']);
        $this->assertNull($asset->fresh()->activeContract());

        $contract->update(['status' => 'Ativo']);

        $this->assertSame($contract->id, $asset->fresh()->activeContract()?->id);
    }
}
