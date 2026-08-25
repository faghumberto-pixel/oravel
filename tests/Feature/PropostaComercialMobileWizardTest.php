<?php

namespace Tests\Feature;

use App\Livewire\PropostaComercialMobile;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Wizard mobile do vendedor de campo -- mesmo padrão estrutural de
 * EquipmentDamageMobile. Cobre a criação incremental de rascunho, adição
 * de itens equipamento + serviço, e o Gate bloqueando quem não tem
 * permissão de criar proposta.
 */
class PropostaComercialMobileWizardTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Mobile Proposta '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_proposta_comercial', 'tabela_solicitacao_locacao'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Mobile Proposta', 'slug' => 'tenant-mobile-proposta-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    public function test_mount_creates_draft_proposta_and_adds_items_of_both_types(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Gerador']);

        $this->actingAs($admin);

        $component = Livewire::test(PropostaComercialMobile::class)
            ->set('clientId', $client->id)
            ->call('saveClient')
            ->assertSet('step', 2);

        $proposta = PropostaComercial::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame($client->id, $proposta->client_id);

        $component
            ->set('itemType', PropostaComercialItem::TYPE_EQUIPAMENTO)
            ->set('itemAssetCategoryId', $category->id)
            ->set('itemDescription', 'Gerador 180 kVA')
            ->set('itemQuantity', 1)
            ->set('itemUnitPrice', 5000)
            ->call('addItem');

        $component
            ->set('itemType', PropostaComercialItem::TYPE_SERVICO)
            ->set('itemDescription', 'Técnico 24h dedicado')
            ->set('itemQuantity', 1)
            ->set('itemUnitPrice', 8000)
            ->call('addItem');

        $this->assertSame(2, $proposta->fresh()->items()->count());
        $this->assertSame('13000.00', $proposta->fresh()->total_value);
    }

    public function test_cannot_add_equipamento_item_without_category(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste']);
        $this->actingAs($admin);

        Livewire::test(PropostaComercialMobile::class)
            ->set('clientId', $client->id)
            ->call('saveClient')
            ->set('itemType', PropostaComercialItem::TYPE_EQUIPAMENTO)
            ->set('itemDescription', 'Gerador sem categoria')
            ->set('itemQuantity', 1)
            ->set('itemUnitPrice', 100)
            ->call('addItem')
            ->assertHasErrors(['itemAssetCategoryId']);
    }

    public function test_enviar_para_comercial_from_wizard_transitions_status(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste']);
        $this->actingAs($admin);

        $component = Livewire::test(PropostaComercialMobile::class)
            ->set('clientId', $client->id)
            ->call('saveClient')
            ->set('itemType', PropostaComercialItem::TYPE_SERVICO)
            ->set('itemDescription', 'Motorista')
            ->set('itemQuantity', 1)
            ->set('itemUnitPrice', 500)
            ->call('addItem')
            ->call('goToStep', 3)
            ->call('saveTerms')
            ->assertSet('step', 4)
            ->call('enviar');

        $proposta = PropostaComercial::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL, $proposta->status);
    }

    public function test_gate_blocks_user_without_permission(): void
    {
        [$tenant] = $this->makeTenantAdmin();

        $plan = Plan::create([
            'name' => 'Plano Sem Feature '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);
        $tenantSemFeature = Tenant::create([
            'name' => 'Tenant Sem Feature', 'slug' => 'tenant-sem-feature-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $userSemPermissao = User::create([
            'name' => 'Sem Permissão', 'email' => 'sem-permissao-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenantSemFeature->id,
        ]);
        $userSemPermissao->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();

        $this->actingAs($userSemPermissao);

        Livewire::test(PropostaComercialMobile::class)->assertForbidden();
    }
}
