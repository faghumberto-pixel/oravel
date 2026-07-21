<?php

namespace Tests\Feature;

use App\Filament\Resources\EquipmentDamageResource;
use App\Filament\Resources\EquipmentDamageResource\Pages\ViewEquipmentDamage;
use App\Models\Asset;
use App\Models\Client;
use App\Models\EquipmentDamage;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Item 7 da auditoria POP: liga o orçamento indenizatório (App\Models\Quote,
 * já com quotable polimórfico desde o item 2) na avaria que o originou --
 * antes disso o vínculo existia no schema mas nunca era usado por nenhuma
 * tela. Cobre POP 5 ("orçamento indenizatório quando o dano é do cliente")
 * e a extensão do POP 6 (mau_uso também alimenta esse fluxo).
 */
class EquipmentDamageIndemnityQuoteTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Indenizacao '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_equipment_damages', 'tabela_assets', 'tabela_maintenance_orders', 'tabela_quotes'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Indenizacao '.uniqid(), 'slug' => 'tenant-indenizacao-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function makeAsset(Tenant $tenant, ?Client $client = null): Asset
    {
        return Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Teste', 'tag' => 'AST-'.uniqid(),
            'status' => 'locado', 'client_id' => $client?->id,
        ]);
    }

    private function makeOrder(Tenant $tenant, Asset $asset, User $user): MaintenanceOrder
    {
        return MaintenanceOrder::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $asset->id,
            'technician_id' => $user->id,
            'description' => 'OS de teste',
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);
    }

    private function makeDamage(Tenant $tenant, Asset $asset, MaintenanceOrder $order, User $user, string $cause): EquipmentDamage
    {
        return EquipmentDamage::create([
            'tenant_id' => $tenant->id,
            'maintenance_order_id' => $order->id,
            'asset_id' => $asset->id,
            'reported_by_user_id' => $user->id,
            'severity' => EquipmentDamage::SEVERITY_MODERADA,
            'damage_type' => EquipmentDamage::DAMAGE_TYPE_OUTRO,
            'cause' => $cause,
            'description' => 'Avaria de teste',
            'status' => EquipmentDamage::STATUS_AGUARDANDO_COMERCIAL,
        ]);
    }

    public function test_action_creates_indemnity_quote_linked_to_the_damage_and_redirects_to_edit(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste']);
        $asset = $this->makeAsset($tenant, $client);
        $order = $this->makeOrder($tenant, $asset, $admin);
        $damage = $this->makeDamage($tenant, $asset, $order, $admin, EquipmentDamage::CAUSE_DANO_CLIENTE);

        $this->actingAs($admin);

        Livewire::test(ViewEquipmentDamage::class, ['record' => $damage->id])
            ->assertActionVisible('gerar_orcamento_indenizatorio')
            ->callAction('gerar_orcamento_indenizatorio')
            ->assertHasNoActionErrors();

        $quote = Quote::firstOrFail();
        $this->assertSame(EquipmentDamage::class, $quote->quotable_type);
        $this->assertSame($damage->id, $quote->quotable_id);
        $this->assertSame($client->id, $quote->client_id);
        $this->assertSame(Quote::TYPE_INDENIZATORIO, $quote->type);
        $this->assertSame($admin->id, $quote->assigned_user_id);
        $this->assertSame($tenant->id, $quote->tenant_id);

        $this->assertTrue($damage->quotes()->whereKey($quote->id)->exists());
    }

    public function test_mau_uso_cause_also_allows_generating_an_indemnity_quote(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste']);
        $asset = $this->makeAsset($tenant, $client);
        $order = $this->makeOrder($tenant, $asset, $admin);
        $damage = $this->makeDamage($tenant, $asset, $order, $admin, EquipmentDamage::CAUSE_MAU_USO);

        $this->actingAs($admin);

        Livewire::test(ViewEquipmentDamage::class, ['record' => $damage->id])
            ->assertActionVisible('gerar_orcamento_indenizatorio');
    }

    public function test_desgaste_natural_cause_never_offers_the_indemnity_quote_action(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste']);
        $asset = $this->makeAsset($tenant, $client);
        $order = $this->makeOrder($tenant, $asset, $admin);
        $damage = $this->makeDamage($tenant, $asset, $order, $admin, EquipmentDamage::CAUSE_DESGASTE_NATURAL);

        $this->actingAs($admin);

        Livewire::test(ViewEquipmentDamage::class, ['record' => $damage->id])
            ->assertActionHidden('gerar_orcamento_indenizatorio');
    }

    public function test_action_is_hidden_once_a_quote_already_exists_for_the_damage(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste']);
        $asset = $this->makeAsset($tenant, $client);
        $order = $this->makeOrder($tenant, $asset, $admin);
        $damage = $this->makeDamage($tenant, $asset, $order, $admin, EquipmentDamage::CAUSE_DANO_CLIENTE);

        $this->actingAs($admin);

        Quote::create([
            'quotable_type' => EquipmentDamage::class,
            'quotable_id' => $damage->id,
            'client_id' => $client->id,
            'type' => Quote::TYPE_INDENIZATORIO,
        ]);

        Livewire::test(ViewEquipmentDamage::class, ['record' => $damage->id])
            ->assertActionHidden('gerar_orcamento_indenizatorio');
    }

    public function test_view_page_renders_the_linked_quote_section_without_errors(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste']);
        $asset = $this->makeAsset($tenant, $client);
        $order = $this->makeOrder($tenant, $asset, $admin);
        $damage = $this->makeDamage($tenant, $asset, $order, $admin, EquipmentDamage::CAUSE_DANO_CLIENTE);

        $this->actingAs($admin);

        Quote::create([
            'quotable_type' => EquipmentDamage::class,
            'quotable_id' => $damage->id,
            'client_id' => $client->id,
            'type' => Quote::TYPE_INDENIZATORIO,
        ]);

        $response = $this->get(EquipmentDamageResource::getUrl('view', ['record' => $damage]));

        $response->assertOk();
        $response->assertSee('Orçamento Indenizatório');
        $response->assertSee('Indenizatório');
    }

    public function test_asset_without_a_linked_client_blocks_quote_creation_with_a_clear_error(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant, client: null);
        $order = $this->makeOrder($tenant, $asset, $admin);
        $damage = $this->makeDamage($tenant, $asset, $order, $admin, EquipmentDamage::CAUSE_DANO_CLIENTE);

        $this->actingAs($admin);

        Livewire::test(ViewEquipmentDamage::class, ['record' => $damage->id])
            ->callAction('gerar_orcamento_indenizatorio');

        $this->assertSame(0, Quote::count());
    }
}
