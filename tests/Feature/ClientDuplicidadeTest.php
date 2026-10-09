<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource\Pages\CreateClient;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ClientDuplicidade;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class ClientDuplicidadeTest extends TestCase
{
    use DatabaseTransactions;

    private function empresa(): array
    {
        $plan = Plan::create(['name' => 'Plano '.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_clients' => true]]);
        $tenant = Tenant::create(['name' => 'Locadora '.uniqid(), 'slug' => 'loc-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'email_verified_at' => now(), 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function existente(Tenant $tenant): Client
    {
        return Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Construtora São João Ltda', 'fantasy_name' => 'São João Obras',
            'document' => '12.345.678/0001-90', 'state_registration' => '123.456.789.000', 'municipal_registration' => '998877',
            'email' => 'Contato@SaoJoao.com.br', 'phone' => '(19) 3333-4444', 'whatsapp' => '(19) 99999-8888',
            'address' => 'Rua das Flores, 100', 'city' => 'Campinas', 'state' => 'SP',
        ]);
    }

    public function test_detecta_cada_campo_repetido_ignorando_acento_pontuacao_e_caixa(): void
    {
        [$tenant] = $this->empresa();
        $this->existente($tenant);

        $conflitos = ClientDuplicidade::conflitos([
            'name' => 'CONSTRUTORA SAO JOAO LTDA', 'fantasy_name' => 'sao joao obras', 'document' => '12345678000190',
            'state_registration' => '123456789000', 'municipal_registration' => '998877', 'email' => 'contato@saojoao.com.br',
            'phone' => '19 99999 8888', 'address' => 'rua das flores, 100', 'city' => 'campinas', 'state' => 'sp',
        ], $tenant->id);

        foreach (['name', 'fantasy_name', 'document', 'state_registration', 'municipal_registration', 'email', 'phone', 'address'] as $campo) {
            $this->assertArrayHasKey($campo, $conflitos, "nao detectou duplicidade em {$campo}");
        }
    }

    public function test_cliente_diferente_nao_gera_conflito_e_isento_nao_conta(): void
    {
        [$tenant] = $this->empresa();
        $existente = $this->existente($tenant);
        $existente->update(['state_registration' => 'ISENTO']);

        $this->assertSame([], ClientDuplicidade::conflitos([
            'name' => 'Outra Empresa SA', 'document' => '98.765.432/0001-10', 'state_registration' => 'Isento',
            'email' => 'outro@empresa.com', 'phone' => '(11) 4000-1000', 'address' => 'Av. Brasil, 5', 'city' => 'Campinas', 'state' => 'SP',
        ], $tenant->id));
    }

    public function test_o_proprio_cadastro_e_outra_empresa_nao_contam(): void
    {
        [$tenant] = $this->empresa();
        [$outraEmpresa] = $this->empresa();
        $existente = $this->existente($tenant);

        $this->assertSame([], ClientDuplicidade::conflitos(['name' => $existente->name, 'document' => $existente->document, 'email' => $existente->email], $tenant->id, $existente->id));
        $this->assertSame([], ClientDuplicidade::conflitos(['name' => $existente->name, 'document' => $existente->document], $outraEmpresa->id));
    }

    public function test_formulario_recusa_cliente_duplicado(): void
    {
        [$tenant, $admin] = $this->empresa();
        $this->existente($tenant);
        $this->actingAs($admin);

        Livewire::test(CreateClient::class)
            ->fillForm(['name' => 'Construtora Sao Joao Ltda', 'document' => '12345678000190', 'email' => 'contato@saojoao.com.br'])
            ->call('create')
            ->assertHasFormErrors(['name', 'document', 'email']);

        $this->assertSame(1, Client::where('tenant_id', $tenant->id)->count());
    }
}
