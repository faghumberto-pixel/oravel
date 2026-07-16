<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource;
use App\Filament\Resources\ChecklistGroupResource;
use App\Filament\Resources\ChecklistGroupResource\Pages\EditChecklistGroup;
use App\Models\Asset;
use App\Models\ChecklistGroup;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Investigacao do usuario: "quando faco qualquer alteracao em um tenant,
 * todos os outros tenants sofrem a mesma alteracao". Nao era escrita
 * cruzando tenants (confirmado com edicao real via Livewire, isolada
 * corretamente) -- era o super admin vendo os dados de TODOS os tenants
 * misturados nas listagens (bypass de leitura documentado e intencional em
 * BelongsToTenant), sem coluna nenhuma indicando de qual empresa e cada
 * registro. Corrigido adicionando uma coluna "Tenant", visivel so pra super
 * admin, nas telas principais (Ativos, OS, Contratos, Clientes).
 */
class SuperAdminTenantColumnTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(): Plan
    {
        return Plan::create([
            'name' => 'Plano '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets'],
        ]);
    }

    private function makeTenant(Plan $plan, string $name): Tenant
    {
        return Tenant::create([
            'name' => $name, 'slug' => str($name)->slug().'-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    private function makeTenantAdmin(Tenant $tenant): User
    {
        $user = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'email_verified_at' => now(), 'is_approved' => true,
        ]);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $user->assignRole($role);

        return $user;
    }

    private function makeSuperAdmin(): User
    {
        return User::create([
            'name' => 'Super', 'email' => 'humberto@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null, 'email_verified_at' => now(), 'is_approved' => true,
        ]);
    }

    public function test_super_admin_sees_tenant_column_disambiguating_mixed_data(): void
    {
        $plan = $this->makePlan();
        $tenantA = $this->makeTenant($plan, 'Tenant Alpha');
        $tenantB = $this->makeTenant($plan, 'Tenant Beta');
        Asset::create(['tenant_id' => $tenantA->id, 'name' => 'Ativo de A', 'tag' => 'A-1', 'status' => 'disponivel']);
        Asset::create(['tenant_id' => $tenantB->id, 'name' => 'Ativo de B', 'tag' => 'B-1', 'status' => 'disponivel']);

        $this->actingAs($this->makeSuperAdmin());

        $response = $this->get(AssetResource::getUrl());

        $response->assertOk();
        $response->assertSee('Ativo de A');
        $response->assertSee('Ativo de B');
        // A coluna precisa aparecer com o nome de CADA tenant, pra deixar
        // claro que sao registros de empresas diferentes na mesma lista.
        $response->assertSee('Tenant Alpha');
        $response->assertSee('Tenant Beta');
    }

    public function test_normal_tenant_admin_does_not_see_tenant_column(): void
    {
        $tenant = $this->makeTenant($this->makePlan(), 'Tenant Unico');
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Unico', 'tag' => 'U-1', 'status' => 'disponivel']);

        $this->actingAs($this->makeTenantAdmin($tenant));

        $response = $this->get(AssetResource::getUrl());

        $response->assertOk();
        $response->assertSee('Ativo Unico');
        // Nome do proprio tenant nao deveria aparecer como COLUNA da tabela
        // -- ele ja sabe de qual empresa e, nao precisa da coluna extra.
        // Nao da mais pra checar so "assertDontSee('Tenant Unico')": desde
        // que o nome do tenant conectado passou a aparecer no topbar (abaixo
        // do logo), o nome aparece ali de qualquer forma -- inclusive 2x na
        // pagina inteira, porque o Filament renderiza o brand-logo duas
        // vezes (sidebar desktop + topbar mobile, alternados por CSS, nao
        // JS). O que importa aqui e' NAO ter uma 3a/4a ocorrencia vinda da
        // coluna da tabela.
        $this->assertSame(2, substr_count($response->getContent(), 'Tenant Unico'), 'nome do tenant deveria aparecer so nas 2 copias responsivas do topbar, nao tambem como coluna da tabela');
    }

    /**
     * Cenario relatado pelo usuario: "editei o Grupo de Equipamento em um
     * tenant e todos os outros sofreram a mesma alteracao". Os grupos de
     * checklist tem nomes IDENTICOS em todo tenant por design (templates
     * padrao) -- sem a coluna Tenant, e o pior caso possivel de confusao,
     * ja que nao ha nenhum outro campo pra diferenciar as 8 linhas
     * "Geradores de Energia" na lista do super admin. Editar uma delas
     * continua isolado (nao muda as outras 7) -- so faltava deixar visivel
     * qual e qual.
     */
    public function test_editing_a_checklist_group_does_not_affect_same_named_groups_in_other_tenants(): void
    {
        $plan = $this->makePlan();
        $tenantA = $this->makeTenant($plan, 'Tenant Alpha');
        $tenantB = $this->makeTenant($plan, 'Tenant Beta');

        $groupA = ChecklistGroup::create(['tenant_id' => $tenantA->id, 'name' => 'Geradores de Energia', 'description' => 'Original']);
        $groupB = ChecklistGroup::create(['tenant_id' => $tenantB->id, 'name' => 'Geradores de Energia', 'description' => 'Original']);

        $this->actingAs($this->makeSuperAdmin());

        $response = $this->get(ChecklistGroupResource::getUrl());
        $response->assertOk();
        $response->assertSee('Tenant Alpha');
        $response->assertSee('Tenant Beta');

        Livewire::test(EditChecklistGroup::class, ['record' => $groupA->id])
            ->set('data.description', 'Alterado só no Alpha')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('Alterado só no Alpha', $groupA->fresh()->description);
        $this->assertEquals('Original', $groupB->fresh()->description);
    }
}
