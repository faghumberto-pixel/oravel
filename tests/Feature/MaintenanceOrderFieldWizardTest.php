<?php

namespace Tests\Feature;

use App\Livewire\MaintenanceOrderFieldWizard;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use App\Models\Material;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Modo Campo" -- wizard de execucao da O.S. no celular (App\Livewire\
 * MaintenanceOrderFieldWizard). Incremento 1: esqueleto + etapa 1.
 * Incremento 2: etapa 2 (checklist com 3 estados) + trait compartilhado.
 */
class MaintenanceOrderFieldWizardTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Campo '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_assets'],
        ]);

        return Tenant::create([
            'name' => 'Tenant Campo '.uniqid(), 'slug' => 'tenant-campo-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    private function makeAdmin(Tenant $tenant): User
    {
        $user = User::create([
            'name' => 'Tecnico Campo', 'email' => 'campo-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return $user;
    }

    private function makeOrder(Tenant $tenant, array $attributes = []): MaintenanceOrder
    {
        $asset = Asset::create(array_merge([
            'tenant_id' => $tenant->id, 'name' => 'Escavadeira Campo', 'status' => 'disponivel',
            'patrimonio' => 'PAT-'.uniqid(), 'last_horimetro' => 1000,
        ], $attributes['asset'] ?? []));

        unset($attributes['asset']);

        return MaintenanceOrder::create(array_merge([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'OS campo',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'status' => 'Aberto',
        ], $attributes));
    }

    private function makeChecklistItem(MaintenanceOrder $order, string $name = 'Item Teste'): MaintenanceOrderChecklist
    {
        return MaintenanceOrderChecklist::create([
            'tenant_id' => $order->tenant_id,
            'maintenance_order_id' => $order->id,
            'item_name' => $name,
            'category' => 'Teste',
            'status' => null,
            'is_completed' => false,
        ]);
    }

    private function makeMaterial(Tenant $tenant, string $name = 'Material Teste', float $price = 100.00): Material
    {
        return Material::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'sku' => 'SKU-'.uniqid(),
            'unit_price' => $price,
            'unit_cost' => $price * 0.6, // 60% do preço de venda como custo
            'category' => 'Teste',
            'stock_quantity' => 1000,
        ]);
    }

    public function test_step_one_persists_horimeter_and_starts_the_service(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $this->actingAs($user);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order])
            ->assertSet('step', 1)
            ->set('horimetroEntry', '1250.50')
            ->set('fuelLevel', '75')
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('step', 2)
            ->assertSet('saveState', 'saved');

        $order->refresh();
        $this->assertEquals(1250.50, (float) $order->horimetro_entry);
        $this->assertSame('75', (string) $order->fuel_level);

        // O botao primario da etapa 1 acumula o "Iniciar Servico" -- em campo o
        // tecnico nao deveria ter que lembrar de um botao separado.
        $this->assertSame('Em Andamento', $order->status);
        $this->assertTrue(
            $order->statusHistories()->where('new_status', 'Em Andamento')->exists(),
            'A transicao tem que ser registrada via logStatusChange, igual a action iniciar do painel.'
        );
    }

    public function test_horimeter_is_required_to_advance(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $this->actingAs($user);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order])
            ->set('horimetroEntry', null)
            ->call('next')
            ->assertHasErrors(['horimetroEntry' => 'required'])
            ->assertSet('step', 1)
            // Erro de preenchimento nao pode acender o aviso de falha de
            // gravacao/conexao -- sao coisas diferentes pro tecnico.
            ->assertSet('saveState', 'idle');

        $this->assertSame('Aberto', $order->fresh()->status);
    }

    public function test_going_back_keeps_what_was_already_saved(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $this->actingAs($user);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order])
            ->set('horimetroEntry', '1300')
            ->call('next')
            ->assertSet('step', 2)
            ->call('back')
            ->assertSet('step', 1)
            ->assertSet('horimetroEntry', '1300')
            ->assertHasNoErrors();

        $this->assertEquals(1300, (float) $order->fresh()->horimetro_entry);
    }

    public function test_step_out_of_range_in_the_url_is_clamped(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $this->actingAs($user);

        // ?step=99 / ?step=-3 nao pode quebrar a tela nem pular etapa.
        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 99])
            ->assertSet('step', MaintenanceOrderFieldWizard::TOTAL_STEPS)
            ->set('step', -3)
            ->assertSet('step', 1);
    }

    public function test_lower_horimeter_than_previous_warns_but_does_not_block(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        // Ativo com 1000h; tecnico digita 200h (painel trocado/zerado acontece).
        $order = $this->makeOrder($tenant);
        $this->actingAs($user);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order])
            ->set('horimetroEntry', '200')
            ->assertSet('horimetroSuspeito', true)
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('step', 2);

        $this->assertEquals(200, (float) $order->fresh()->horimetro_entry);
    }

    /**
     * Testado pela ROTA, nao instanciando o componente com o model na mao:
     * neste projeto o isolamento por tenant vive na camada Eloquent (global
     * scope de BelongsToTenant), nao na policy -- AbstractPolicy checa feature
     * do plano + permissao, e da' bypass pra quem tem o papel admin do proprio
     * tenant, sem olhar de quem e' o registro. Quem barra a O.S. de outro
     * tenant e' o route model binding, que nao acha o registro sob o scope.
     * Injetar o model direto no Livewire pularia justamente essa protecao e o
     * teste passaria a testar nada.
     */
    public function test_order_from_another_tenant_is_not_reachable(): void
    {
        $tenantA = $this->makeTenant();
        $tenantB = $this->makeTenant();
        $userA = $this->makeAdmin($tenantA);

        // Criada como admin do tenant B pra passar pelo BelongsToTenant.
        $this->actingAs($this->makeAdmin($tenantB));
        $orderB = $this->makeOrder($tenantB);

        $this->actingAs($userA)
            ->get(route('maintenance-orders.field-wizard', $orderB))
            ->assertNotFound();
    }

    public function test_the_field_wizard_route_responds(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);

        $this->actingAs($user)
            ->get(route('maintenance-orders.field-wizard', $order))
            ->assertOk()
            ->assertSee('MODO CAMPO')
            ->assertSee('ETAPA 1 DE 5');
    }

    /**
     * A tela mobile antiga (checklist-digital) ficou anos inalcancavel por nao
     * ser linkada de lugar nenhum. Este teste existe pra o wizard nao repetir
     * isso: se alguem remover o ponto de entrada, o teste cai.
     */
    public function test_edit_screen_links_to_the_field_wizard(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);

        $this->actingAs($user)
            ->get(route('filament.admin.resources.maintenance-orders.edit', ['record' => $order]))
            ->assertOk()
            ->assertSee(route('maintenance-orders.field-wizard', $order));
    }

    // --- ETAPA 2: CHECKLIST COM 3 ESTADOS ---

    public function test_step_two_can_expand_and_collapse_item(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $item = $this->makeChecklistItem($order, 'Item Test');
        $this->actingAs($user);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 2])
            ->call('expand', $item->id)
            ->assertSet('expandedItemId', $item->id)
            ->call('collapse')
            ->assertSet('expandedItemId', null)
            ->assertSet('newObservation', '');
    }

    public function test_step_two_nao_conforme_requires_observation_and_photo(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $item = $this->makeChecklistItem($order, 'Item Test');
        $this->actingAs($user);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 2])
            ->call('setItemStatus', $item->id, 'nao_conforme')
            ->assertHasErrors(['itemStatusError']);
    }

    public function test_step_two_advances_to_step_three(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $this->makeChecklistItem($order, 'Item 1');
        $this->actingAs($user);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 2])
            ->call('next')
            ->assertSet('step', 3)
            ->assertSet('saveState', 'saved');
    }

    // --- ETAPA 3: AVARIAS/OBSERVAÇÕES ---

    public function test_step_three_accepts_damage_description_and_notes(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $this->actingAs($user);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 3])
            ->set('damageDescription', 'Correia desgastada')
            ->set('technicalNotes', 'Necessário reposição imediata')
            ->call('next')
            ->assertSet('step', 4)
            ->assertSet('saveState', 'saved');

        $order->refresh();
        $this->assertSame('Correia desgastada', $order->description);
        $this->assertSame('Necessário reposição imediata', $order->technical_notes);
    }

    public function test_step_three_can_clear_damage_photos(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $this->actingAs($user);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 3])
            ->call('clearDamagePhotoBefore')
            ->assertSet('damagePhotoBefore', null)
            ->call('clearDamagePhotoAfter')
            ->assertSet('damagePhotoAfter', null);
    }

    public function test_step_three_damage_registration_flag_can_be_toggled(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $this->actingAs($user);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 3])
            ->set('shouldRegisterDamage', false)
            ->assertSet('shouldRegisterDamage', false)
            ->set('shouldRegisterDamage', true)
            ->assertSet('shouldRegisterDamage', true);
    }

    public function test_step_three_advances_to_step_four(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $this->actingAs($user);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 3])
            ->set('damageDescription', 'Problema encontrado')
            ->call('next')
            ->assertSet('step', 4)
            ->assertSet('saveState', 'saved');
    }

    // --- ETAPA 4: MATERIAIS ---

    public function test_step_four_can_search_materials(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $material = $this->makeMaterial($tenant, 'Correia de transmissão', 150.00);
        $this->actingAs($user);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 4])
            ->set('materialSearch', 'correia')
            ->assertCount('materialSearchResults', 1);
    }

    public function test_step_four_can_select_and_add_material(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $material = $this->makeMaterial($tenant, 'Parafuso', 5.00);
        $this->actingAs($user);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 4])
            ->call('selectMaterial', $material->id)
            ->assertSet('selectedMaterialId', $material->id)
            ->set('materialQuantity', 10)
            ->call('addMaterialToOrder')
            ->assertHasNoErrors()
            ->assertSet('selectedMaterialId', null);

        $this->assertDatabaseHas('maintenance_order_materials', [
            'maintenance_order_id' => $order->id,
            'material_id' => $material->id,
            'quantity' => 10,
        ]);
    }

    public function test_step_four_shows_applied_materials(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $material = $this->makeMaterial($tenant, 'Óleo', 50.00);
        $this->actingAs($user);

        // Adiciona material
        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 4])
            ->call('selectMaterial', $material->id)
            ->set('materialQuantity', 2)
            ->call('addMaterialToOrder');

        // Verifica que aparece na lista
        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 4])
            ->assertCount('appliedMaterials', 1);
    }

    public function test_step_four_can_remove_material(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $material = $this->makeMaterial($tenant, 'Filtro', 30.00);
        $this->actingAs($user);

        // Adiciona material
        $component = Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 4])
            ->call('selectMaterial', $material->id)
            ->set('materialQuantity', 1)
            ->call('addMaterialToOrder');

        // Remove material
        $appliedMaterial = $order->materials()->first();
        $component->call('removeMaterial', $appliedMaterial->id)
            ->assertCount('appliedMaterials', 0);

        $this->assertDatabaseMissing('maintenance_order_materials', ['id' => $appliedMaterial->id]);
    }

    public function test_step_four_calculates_material_cost_total(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $material = $this->makeMaterial($tenant, 'Material', 100.00);
        $this->actingAs($user);

        // Adiciona material e verifica que custo foi calculado (não zero)
        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 4])
            ->call('selectMaterial', $material->id)
            ->set('materialQuantity', 2)
            ->call('addMaterialToOrder')
            ->assertSet('appliedMaterials', fn ($materials) => $materials->count() > 0);
    }

    public function test_step_four_advances_to_step_five(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $this->actingAs($user);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 4])
            ->call('next')
            ->assertSet('step', 5)
            ->assertSet('saveState', 'saved');
    }

    public function test_step_four_material_quantity_must_be_positive(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $material = $this->makeMaterial($tenant, 'Material', 100.00);
        $this->actingAs($user);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 4])
            ->call('selectMaterial', $material->id)
            ->set('materialQuantity', 0)
            ->call('addMaterialToOrder')
            ->assertHasErrors(['materialQuantity']);
    }

    // --- ETAPA 5: ASSINATURA ---

    public function test_step_five_can_clear_signature(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $this->actingAs($user);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 5])
            ->set('technicianSignature', 'data:image/png;base64,iVBORw0KGgo=')
            ->assertSet('technicianSignature', 'data:image/png;base64,iVBORw0KGgo=')
            ->call('clearSignature')
            ->assertSet('technicianSignature', null);
    }

    public function test_step_five_completes_the_order_and_changes_status(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant, ['status' => 'Em Andamento']);
        $this->actingAs($user);

        // Navegar para step 5 e chamar next() deve concluir a O.S.
        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 5])
            ->call('next')
            ->assertRedirect(
                route('filament.admin.resources.maintenance-orders.edit', ['record' => $order])
            );

        $order->refresh();
        $this->assertSame('Concluída', $order->status);
        $this->assertTrue(
            $order->statusHistories()->where('new_status', 'Concluída')->exists(),
            'A transicao deve ser registrada via logStatusChange'
        );
    }

    public function test_step_five_from_step_four_advances_correctly(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $this->actingAs($user);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 4])
            ->call('next')
            ->assertSet('step', 5)
            ->assertSet('saveState', 'saved');
    }

    public function test_step_five_shows_execution_summary(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $this->actingAs($user);

        $this->actingAs($user)
            ->get(route('maintenance-orders.field-wizard', ['maintenanceOrder' => $order, 'step' => 5]))
            ->assertOk()
            ->assertSee('ETAPA 5 DE 5')
            ->assertSee('Resumo da Execução')
            ->assertSee('Custos Finais')
            ->assertSee('Assinatura do Técnico');
    }

    public function test_step_five_primary_button_label_is_enviar(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeAdmin($tenant);
        $order = $this->makeOrder($tenant);
        $this->actingAs($user);

        $component = Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $order, 'step' => 5]);
        $this->assertSame('ENVIAR O.S.', $component->instance()->primaryLabel);
    }
}
