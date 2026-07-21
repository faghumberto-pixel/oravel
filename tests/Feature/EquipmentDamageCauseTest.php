<?php

namespace Tests\Feature;

use App\Filament\Resources\EquipmentDamageResource\Pages\ViewEquipmentDamage;
use App\Filament\Resources\MaintenanceOrderResource\Concerns\StoresPhotoEvidence;
use App\Livewire\EquipmentDamageMobile;
use App\Models\Asset;
use App\Models\EquipmentDamage;
use App\Models\EquipmentMovement;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Item 6 da auditoria POP: causa estruturada (desgaste_natural/mau_uso/
 * dano_cliente) em EquipmentDamage, substituindo o texto livre. Cobre os 3
 * pontos de criacao (StoresPhotoEvidence, EquipmentDamageMobile) mais a
 * revisao do supervisor (ViewEquipmentDamage::confirmar).
 */
class EquipmentDamageCauseTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Causa '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_equipment_damages', 'tabela_assets', 'tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Causa '.uniqid(), 'slug' => 'tenant-causa-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function makeAsset(Tenant $tenant): Asset
    {
        return Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Teste', 'tag' => 'AST-'.uniqid(),
            'status' => 'locado',
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

    public function test_mau_uso_evidence_auto_classifies_cause_but_generic_avaria_stays_unclassified(): void
    {
        Storage::fake('public');

        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $order = $this->makeOrder($tenant, $asset, $admin);

        $this->actingAs($admin);

        $harness = new class
        {
            use StoresPhotoEvidence;

            public function extract(array &$data): void
            {
                $this->extractPhotoEvidences($data);
            }

            public function persist(MaintenanceOrder $order): void
            {
                $this->persistPhotoEvidences($order);
            }
        };

        $fakeImage = 'data:image/png;base64,'.base64_encode('fake-image-bytes');

        $data = [
            'extra_evidences' => [
                [
                    'category' => 'lateral',
                    'severity' => 'mau_uso',
                    'observation' => null,
                    'damage_severity' => EquipmentDamage::SEVERITY_MODERADA,
                    'damage_type' => EquipmentDamage::DAMAGE_TYPE_ESTRUTURAL,
                    'photo' => ['image' => $fakeImage],
                ],
                [
                    'category' => 'traseira',
                    'severity' => 'avaria',
                    'observation' => null,
                    'damage_severity' => EquipmentDamage::SEVERITY_LEVE,
                    'damage_type' => EquipmentDamage::DAMAGE_TYPE_OUTRO,
                    'photo' => ['image' => $fakeImage],
                ],
            ],
        ];

        $harness->extract($data);
        $harness->persist($order);

        $mauUso = EquipmentDamage::where('damage_type', EquipmentDamage::DAMAGE_TYPE_ESTRUTURAL)->firstOrFail();
        $this->assertSame(EquipmentDamage::CAUSE_MAU_USO, $mauUso->cause);

        $avariaGenerica = EquipmentDamage::where('damage_type', EquipmentDamage::DAMAGE_TYPE_OUTRO)->firstOrFail();
        $this->assertNull($avariaGenerica->cause);
    }

    public function test_mobile_flow_creates_damage_with_optional_cause_and_allows_updating_it(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $order = $this->makeOrder($tenant, $asset, $admin);
        $movement = EquipmentMovement::create([
            'tenant_id' => $tenant->id,
            'maintenance_order_id' => $order->id,
            'asset_id' => $asset->id,
            'type' => EquipmentMovement::TYPE_DESMOBILIZACAO,
            'status' => EquipmentMovement::STATUS_EM_ANDAMENTO,
        ]);

        $this->actingAs($admin);

        // Sem informar causa -- fica nulo/nao classificado, ok pra inspetor
        // que nao sabe a causa com certeza no momento da devolucao.
        Livewire::test(EquipmentDamageMobile::class, ['equipmentMovement' => $movement])
            ->set('severity', EquipmentDamage::SEVERITY_MODERADA)
            ->set('damageType', EquipmentDamage::DAMAGE_TYPE_HIDRAULICO)
            ->set('description', 'Vazamento identificado na devolucao')
            ->call('saveDamageDetails')
            ->assertHasNoErrors();

        $damage = EquipmentDamage::firstOrFail();
        $this->assertNull($damage->cause);

        // Atualizando o mesmo registro com uma causa definida.
        Livewire::test(EquipmentDamageMobile::class, ['equipmentMovement' => $movement])
            ->set('damage', $damage)
            ->set('severity', EquipmentDamage::SEVERITY_MODERADA)
            ->set('damageType', EquipmentDamage::DAMAGE_TYPE_HIDRAULICO)
            ->set('cause', EquipmentDamage::CAUSE_DANO_CLIENTE)
            ->set('description', 'Vazamento identificado na devolucao')
            ->call('saveDamageDetails')
            ->assertHasNoErrors();

        $this->assertSame(EquipmentDamage::CAUSE_DANO_CLIENTE, $damage->fresh()->cause);
    }

    public function test_mobile_flow_rejects_invalid_cause_value(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $order = $this->makeOrder($tenant, $asset, $admin);
        $movement = EquipmentMovement::create([
            'tenant_id' => $tenant->id,
            'maintenance_order_id' => $order->id,
            'asset_id' => $asset->id,
            'type' => EquipmentMovement::TYPE_DESMOBILIZACAO,
            'status' => EquipmentMovement::STATUS_EM_ANDAMENTO,
        ]);

        $this->actingAs($admin);

        Livewire::test(EquipmentDamageMobile::class, ['equipmentMovement' => $movement])
            ->set('severity', EquipmentDamage::SEVERITY_LEVE)
            ->set('damageType', EquipmentDamage::DAMAGE_TYPE_OUTRO)
            ->set('cause', 'causa_invalida')
            ->set('description', 'Descricao qualquer')
            ->call('saveDamageDetails')
            ->assertHasErrors(['cause']);
    }

    public function test_supervisor_confirmation_action_sets_the_cause(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $order = $this->makeOrder($tenant, $asset, $admin);

        $damage = EquipmentDamage::create([
            'tenant_id' => $tenant->id,
            'maintenance_order_id' => $order->id,
            'asset_id' => $asset->id,
            'reported_by_user_id' => $admin->id,
            'severity' => EquipmentDamage::SEVERITY_MODERADA,
            'damage_type' => EquipmentDamage::DAMAGE_TYPE_OUTRO,
            'description' => 'Avaria a ser revisada pelo supervisor',
            'status' => EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR,
        ]);

        $this->actingAs($admin);

        Livewire::test(ViewEquipmentDamage::class, ['record' => $damage->id])
            ->callAction('confirmar', data: [
                'severity' => EquipmentDamage::SEVERITY_GRAVE,
                'requires_replacement' => true,
                'cause' => EquipmentDamage::CAUSE_MAU_USO,
            ])
            ->assertHasNoActionErrors();

        $damage->refresh();
        $this->assertSame(EquipmentDamage::CAUSE_MAU_USO, $damage->cause);
        $this->assertSame(EquipmentDamage::STATUS_AGUARDANDO_COMERCIAL, $damage->status);
    }
}
