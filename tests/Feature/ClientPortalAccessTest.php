<?php

namespace Tests\Feature;

use App\Filament\Client\Pages\Auth\Login;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Portal do Cliente (2026-08-25/26): guard 'client' dedicado, separado do
 * guard 'web' usado por User. Client sem portal_access_enabled_at não
 * consegue logar -- é o mesmo mecanismo que a Action "Conceder acesso ao
 * portal" em ClientResource usa para habilitar. A checagem de
 * portal_access_enabled_at vive em App\Filament\Client\Pages\Auth\Login
 * (getCredentialsFromFormData()), não em Auth::attempt() puro -- por isso
 * o teste "sem acesso" passa pela Page real via Livewire, não pelo guard
 * direto (que não sabe dessa regra de negócio).
 */
class ClientPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Portal '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_clients', 'tabela_contracts'],
        ]);

        return Tenant::create([
            'name' => 'Tenant Portal '.uniqid(), 'slug' => 'tenant-portal-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    public function test_client_without_granted_access_cannot_login(): void
    {
        $tenant = $this->makeTenant();

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente Sem Acesso',
            'email' => 'sem-acesso-'.uniqid().'@teste.com', 'password' => 'senha123',
        ]);

        $this->get('/cliente/login');

        Livewire::test(Login::class)
            ->set('data.email', $client->email)
            ->set('data.password', 'senha123')
            ->call('authenticate');

        $this->assertFalse(Auth::guard('client')->check());
    }

    public function test_client_with_granted_access_can_login(): void
    {
        $tenant = $this->makeTenant();

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente Com Acesso',
            'email' => 'com-acesso-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        $this->assertTrue(Auth::guard('client')->attempt([
            'email' => $client->email, 'password' => 'senha123',
        ]));
        $this->assertTrue(Auth::guard('client')->check());
        $this->assertSame($client->id, Auth::guard('client')->id());
    }

    public function test_wrong_password_fails(): void
    {
        $tenant = $this->makeTenant();

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente',
            'email' => 'senha-errada-'.uniqid().'@teste.com', 'password' => 'senhacerta',
            'portal_access_enabled_at' => now(),
        ]);

        $this->assertFalse(Auth::guard('client')->attempt([
            'email' => $client->email, 'password' => 'senhaerrada',
        ]));
    }

    public function test_password_and_remember_token_are_hidden_from_serialization(): void
    {
        $tenant = $this->makeTenant();

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente',
            'email' => 'serializacao-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        $array = $client->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }
}
