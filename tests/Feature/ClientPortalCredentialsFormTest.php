<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource\Pages\EditClient;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class ClientPortalCredentialsFormTest extends TestCase
{
    use DatabaseTransactions;

    private function cenario(): array
    {
        $plan = Plan::create([
            'name' => 'Plano '.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_clients' => true],
        ]);
        $tenant = Tenant::create(['name' => 'Locadora '.uniqid(), 'slug' => 'loc-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'),
            'tenant_id' => $tenant->id, 'email_verified_at' => now(), 'is_approved' => true,
        ]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['tenant_id' => $tenant->id]));
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Portal', 'email' => 'cli-'.uniqid().'@teste.com']);

        return [$admin, $client];
    }

    public function test_operador_define_senha_e_o_cliente_consegue_entrar(): void
    {
        [$admin, $client] = $this->cenario();
        $this->actingAs($admin);

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm(['portal_access_enabled_at' => true, 'password' => 'SenhaForte123'])
            ->call('save')
            ->assertHasNoFormErrors();

        $client->refresh();
        $this->assertNotNull($client->portal_access_enabled_at);
        $this->assertTrue(Auth::guard('client')->attempt(['email' => $client->email, 'password' => 'SenhaForte123']));
    }

    public function test_senha_em_branco_mantem_a_senha_atual(): void
    {
        [$admin, $client] = $this->cenario();
        $client->update(['password' => 'SenhaAntiga123', 'portal_access_enabled_at' => now()]);
        $this->actingAs($admin);

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm(['name' => 'Cliente Portal Renomeado', 'password' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Auth::guard('client')->attempt(['email' => $client->email, 'password' => 'SenhaAntiga123']));
    }

    public function test_acesso_ativo_sem_senha_e_recusado(): void
    {
        [$admin, $client] = $this->cenario();
        $this->actingAs($admin);

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm(['portal_access_enabled_at' => true, 'password' => ''])
            ->call('save')
            ->assertHasFormErrors(['password' => 'required']);
    }

    public function test_email_de_login_nao_pode_repetir_em_outro_cliente_com_acesso(): void
    {
        [$admin, $client] = $this->cenario();
        [, $outro] = $this->cenario();
        $outro->update(['email' => $client->email, 'password' => 'OutraSenha123', 'portal_access_enabled_at' => now()]);
        $this->actingAs($admin);

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm(['portal_access_enabled_at' => true, 'password' => 'SenhaForte123'])
            ->call('save')
            ->assertHasFormErrors(['email']);
    }
}
