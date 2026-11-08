<?php

namespace Tests\Feature;

use App\Dashboards\DashboardRenderer;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DemoPopularPaineisTest extends TestCase
{
    use DatabaseTransactions;

    public function test_popula_os_paineis_por_area_e_remove_so_o_que_criou(): void
    {
        if (! str_contains((string) DB::connection()->getDatabaseName(), 'test')) {
            $this->markTestSkipped('banco nao descartavel');
        }

        $arquivo = storage_path('app/demo-paineis-ids.json');
        File::delete($arquivo);
        config(['oravel.dashboards_modulo' => true]);
        Cache::flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $plano = Plan::create(['name' => 'P'.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_employees' => true, 'tabela_time_clocks' => true, 'tabela_maintenance_plans' => true, 'tabela_purchase_orders' => true, 'tabela_assets' => true]]);
        $t = Tenant::create(['name' => 'Demo Painéis '.uniqid(), 'slug' => 'dp-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Adm', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        Supplier::unguarded(fn () => Supplier::create(['tenant_id' => $t->id, 'name' => 'Fornecedor X']));
        $real = Asset::factory()->create(['tenant_id' => $t->id, 'name' => 'Ativo real', 'horimetro_atual' => 800, 'asset_category_id' => null, 'checklist_group_id' => null]);
        $antes = DB::table('assets')->where('tenant_id', $t->id)->count();

        $this->artisan('demo:popular-paineis', ['--tenant' => $t->name])->assertSuccessful();
        $this->artisan('demo:popular-paineis', ['--tenant' => $t->name])->assertFailed(); // nao duplica

        $this->actingAs($admin);
        $rh = collect(DashboardRenderer::blocks('rh', $admin))->keyBy('key');
        $this->assertGreaterThan(5, $rh['rh.colaboradores_ativos']['payload']['value']);
        $this->assertGreaterThan(0, $rh['rh.ponto_hoje']['payload']['value']);
        $this->assertNotEmpty($rh['rh.certificacoes_a_vencer']['payload']['rows']);

        $compras = collect(DashboardRenderer::blocks('compras', $admin))->keyBy('key');
        $this->assertNotEmpty($compras['compras.pedidos_atrasados']['payload']['rows']);

        $pmp = collect(DashboardRenderer::blocks('planejamento', $admin))->keyBy('key');
        $this->assertGreaterThan(0, $pmp['pmp.preventivas_vencidas']['payload']['value']);
        $this->assertSame($antes, DB::table('assets')->where('tenant_id', $t->id)->count(), 'nao cria nem mexe em ativos');

        $this->artisan('demo:popular-paineis', ['--tenant' => $t->name, '--remover' => true])->assertSuccessful();
        foreach (['employees', 'employee_certifications', 'time_clocks', 'purchase_orders', 'maintenance_plans'] as $tabela) {
            $this->assertSame(0, DB::table($tabela)->where('tenant_id', $t->id)->count(), $tabela);
        }
        $this->assertSame(1, DB::table('suppliers')->where('tenant_id', $t->id)->count(), 'fornecedor existente permanece');
        $this->assertNotNull(Asset::withoutGlobalScopes()->find($real->id));
        $this->assertFileDoesNotExist($arquivo);
    }
}
