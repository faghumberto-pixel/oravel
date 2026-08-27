<?php

namespace Tests\Feature;

use App\Filament\Resources\ContractResource\Pages\CreateContract;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\InternalUnit;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContractBlockedByOverdueCriticalMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Bloqueio Contrato '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_contracts', 'tabela_assets', 'tabela_clients'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Bloqueio Contrato '.uniqid(), 'slug' => 'tenant-bloqueio-contrato-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin Bloqueio Contrato', 'email' => 'admin-bloqueio-contrato-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_creating_contract_for_blocked_asset_is_denied(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Bloqueio']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Bloqueado', 'status' => Asset::STATUS_MANUTENCAO,
            'blocked_by_pmp_at' => now(), 'status_before_pmp_block' => Asset::STATUS_DISPONIVEL,
        ]);

        $this->actingAs($admin);

        Livewire::test(CreateContract::class)
            ->fillForm([
                'cnpj' => '12.345.678/0001-90',
                'client_id' => $client->id,
                'asset_id' => $asset->id,
                'contract_number' => 'CT-BLOQ-001',
                'start_date' => now()->toDateString(),
                'local_tipo' => Contract::LOCAL_TIPO_SEDE_EMPRESA,
                'billing_type' => Contract::BILLING_MENSAL_FIXO,
                'price' => 1000,
            ])
            ->call('create')
            ->assertHasFormErrors(['asset_id']);

        $this->assertSame(0, Contract::where('tenant_id', $tenant->id)->count());
    }

    public function test_creating_contract_for_unblocked_asset_succeeds(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Livre']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Livre', 'status' => Asset::STATUS_DISPONIVEL,
        ]);

        $this->actingAs($admin);

        $internalUnit = InternalUnit::create(['tenant_id' => $tenant->id, 'name' => 'Matriz']);

        Livewire::test(CreateContract::class)
            ->fillForm([
                'cnpj' => '12.345.678/0001-90',
                'client_id' => $client->id,
                'asset_id' => $asset->id,
                'contract_number' => 'CT-LIVRE-001',
                'start_date' => now()->toDateString(),
                'local_tipo' => Contract::LOCAL_TIPO_SEDE_EMPRESA,
                'internal_unit_id' => $internalUnit->id,
                'condicao_ambiente' => Contract::CONDICAO_NORMAL,
                'responsavel_manutencao' => 'locador',
                'billing_type' => Contract::BILLING_MENSAL_FIXO,
                'price' => 1000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Contract::where('tenant_id', $tenant->id)->count());
    }
}
