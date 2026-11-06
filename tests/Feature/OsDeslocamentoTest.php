<?php

namespace Tests\Feature;

use App\Dashboards\Blocks\Manutencao\KmRodados;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Deslocamento da OS: km e veículo viram custo de logística e alimentam o cartão "Quilômetros rodados". */
class OsDeslocamentoTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_maintenance_orders' => true, 'tabela_assets' => true]]);
        $this->tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'dl-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $this->admin = User::create(['name' => 'A', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        $this->admin->forceFill(['email_verified_at' => now()])->save();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]));
        $this->actingAs($this->admin);
    }

    private function ativo(array $extra = []): Asset
    {
        return Asset::unguarded(fn () => Asset::create(array_merge(['tenant_id' => $this->tenant->id, 'name' => 'Ativo '.uniqid(), 'tag' => 'T-'.uniqid(), 'status' => 'disponivel'], $extra)));
    }

    private function os(Asset $ativo, array $extra = []): MaintenanceOrder
    {
        return MaintenanceOrder::unguarded(fn () => MaintenanceOrder::create(array_merge(['tenant_id' => $this->tenant->id, 'asset_id' => $ativo->id, 'os_number' => 'OS-'.uniqid(), 'status' => 'Aberta', 'total_time_seconds' => 0, 'labor_cost' => 100], $extra)));
    }

    public function test_km_e_veiculo_calculam_a_logistica_e_o_total(): void
    {
        $maquina = $this->ativo();
        $caminhao = $this->ativo(['is_vehicle' => true, 'cost_per_km' => 2.5]);
        $os = $this->os($maquina);

        $os->update(['km_traveled' => 40, 'transport_vehicle_id' => $caminhao->id]);

        $os->refresh();
        $this->assertEquals(100.0, (float) $os->logistics_cost); // 40 km x R$ 2,50
        $this->assertEquals(200.0, (float) $os->total_order_cost); // mão de obra 100 + logística 100
        $this->assertEquals(40.0, (float) $os->km_traveled);
    }

    public function test_sem_custo_por_km_o_km_fica_so_registrado_e_logistica_digitada_prevalece(): void
    {
        $maquina = $this->ativo();
        $semCusto = $this->ativo(['is_vehicle' => true]);
        $os = $this->os($maquina);

        $os->update(['km_traveled' => 30, 'transport_vehicle_id' => $semCusto->id]);
        $this->assertEquals(0.0, (float) $os->fresh()->logistics_cost);
        $this->assertEquals(30.0, (float) $os->fresh()->km_traveled);

        $comCusto = $this->ativo(['is_vehicle' => true, 'cost_per_km' => 3]);
        $os->update(['km_traveled' => 50, 'transport_vehicle_id' => $comCusto->id, 'logistics_cost' => 77]);
        $this->assertEquals(77.0, (float) $os->fresh()->logistics_cost, 'quem digitou a logistica na mesma gravacao manda');
    }

    public function test_o_cartao_quilometros_rodados_soma_o_deslocamento_do_mes(): void
    {
        $maquina = $this->ativo();
        $this->os($maquina, ['km_traveled' => 40, 'logistics_cost' => 100]);
        $this->os($maquina, ['km_traveled' => 25.5, 'logistics_cost' => 51]);

        $p = (new KmRodados)->data([]);

        $this->assertSame('66', $p['value']); // 65,5 km arredondado para o formato do cartão
        $this->assertStringContainsString('por atendimento', $p['delta']);
    }
}
