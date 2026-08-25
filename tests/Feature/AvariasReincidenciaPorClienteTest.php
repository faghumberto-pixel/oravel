<?php

namespace Tests\Feature;

use App\Filament\Pages\AvariasReincidencia;
use App\Models\Asset;
use App\Models\Client;
use App\Models\EquipmentDamage;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido do usuário 2026-08-25 (item 3 do roteiro de artefatos comerciais):
 * reincidência de avaria era só por Ativo, não distinguia se o padrão era
 * do equipamento (peça ruim) ou de um cliente específico devolvendo
 * equipamentos danificados com frequência. getReincidenciasPorClienteProperty()
 * agrupa por MaintenanceOrder.client_id, só contando causas cobráveis
 * (mau_uso/dano_cliente) -- desgaste natural nunca é "culpa" do cliente.
 */
class AvariasReincidenciaPorClienteTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Reincidência '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_equipment_damages'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Reincidência '.uniqid(), 'slug' => 'tenant-reincidencia-'.uniqid(),
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

    private function makeDamage(Tenant $tenant, User $reporter, Client $client, string $cause): EquipmentDamage
    {
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo '.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
        ]);
        $os = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'client_id' => $client->id,
            'os_number' => 'OS-'.uniqid(), 'status' => 'concluida', 'maintenance_type' => 'corretiva',
        ]);

        return EquipmentDamage::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_order_id' => $os->id,
            'reported_by_user_id' => $reporter->id,
            'severity' => 'grave', 'damage_type' => 'estrutural', 'cause' => $cause,
            'description' => 'Dano de teste.',
        ]);
    }

    public function test_agrupa_por_cliente_apenas_causas_cobraveis(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $clienteReincidente = Client::create(['tenant_id' => $tenant->id, 'name' => 'Construtora Vega']);
        $outroCliente = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Único']);

        // Cliente reincidente: 2 avarias cobráveis, em ativos DIFERENTES --
        // reincidência por ativo não pegaria isso, só a por cliente.
        $this->makeDamage($tenant, $admin, $clienteReincidente, EquipmentDamage::CAUSE_MAU_USO);
        $this->makeDamage($tenant, $admin, $clienteReincidente, EquipmentDamage::CAUSE_DANO_CLIENTE);

        // Desgaste natural não conta como reincidência cobrável, mesmo em volume.
        $this->makeDamage($tenant, $admin, $clienteReincidente, EquipmentDamage::CAUSE_DESGASTE_NATURAL);
        $this->makeDamage($tenant, $admin, $clienteReincidente, EquipmentDamage::CAUSE_DESGASTE_NATURAL);

        // Cliente com só 1 avaria cobrável não deve aparecer (limiar é 2+).
        $this->makeDamage($tenant, $admin, $outroCliente, EquipmentDamage::CAUSE_MAU_USO);

        $this->actingAs($admin);
        $page = new AvariasReincidencia();
        $page->days = 90;

        $resultado = $page->reincidenciasPorCliente;

        $this->assertCount(1, $resultado);
        $this->assertSame($clienteReincidente->id, $resultado->first()['client']->id);
        $this->assertSame(2, $resultado->first()['total']);
    }
}
