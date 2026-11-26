<?php

namespace Tests\Feature;

use App\Dashboards\DashboardRenderer;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Comando que popula a empresa de teste do Vendas com dados fictícios de CRM: só adiciona, recusa empresa com leads e remove só o que criou. */
class DemoPopularVendasTest extends TestCase
{
    use DatabaseTransactions;

    private string $nome;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.dashboards_modulo' => true]);
        Cache::flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        File::delete(storage_path('app/demo-vendas-ids.json'));
        $this->nome = 'Demo Vendas '.uniqid();
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('app/demo-vendas-ids.json'));
        parent::tearDown();
    }

    private function empresa(string $nome): array
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [
            'tabela_crm_leads' => true, 'tabela_crm_metas' => true, 'tabela_crm_propostas' => true, 'tabela_crm_visitas' => true, 'modulo_paineis_funcao' => true,
        ]]);
        $tenant = Tenant::create(['name' => $nome, 'slug' => 'dv-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function conta(Tenant $t, string $tabela): int
    {
        return DB::table($tabela)->where('tenant_id', $t->id)->count();
    }

    public function test_popula_a_empresa_vazia_e_o_painel_de_vendas_fica_vivo(): void
    {
        [$t, $admin] = $this->empresa($this->nome);
        [$outra] = $this->empresa('Outra '.uniqid());

        $this->assertSame(0, Artisan::call('demo:popular-vendas', ['--tenant' => $this->nome]));

        $this->assertGreaterThanOrEqual(35, $this->conta($t, 'crm_leads'));
        $this->assertSame(8, $this->conta($t, 'crm_metas'));
        $this->assertSame(13, $this->conta($t, 'crm_propostas'));
        $this->assertGreaterThanOrEqual(20, $this->conta($t, 'crm_visitas'));
        $this->assertSame(0, DB::table('crm_leads')->where('tenant_id', $outra->id)->count(), 'a outra empresa não recebe nada');
        $this->assertSame(0, DB::table('crm_leads')->where('tenant_id', $t->id)->whereNull('etapa_id')->count(), 'todo lead ficou ligado a uma etapa do funil');

        $this->actingAs($admin);
        $blocos = collect(DashboardRenderer::blocks('vendas', $admin))->keyBy('key');
        $meta = $blocos['vendas.meta_mes']['payload'];
        $this->assertGreaterThan(40, $meta['value']);
        $this->assertLessThan(90, $meta['value']);
        $this->assertNotEmpty($blocos['vendas.perdas_motivo']['payload']['rows']);
        $this->assertNotEmpty($blocos['vendas.leads_parados']['payload']['items']);
        $this->assertNotEmpty($blocos['comercial.proximos_compromissos']['payload']['events'], 'próximos compromissos tem retornos e propostas que vencem');
        $this->assertNotEmpty($blocos['vendas.meta_vendedor']['payload']['rows']);
        $this->assertGreaterThan(0, $blocos['vendas.visitas_mes']['payload']['value']);
    }

    public function test_nao_roda_duas_vezes_nem_em_empresa_que_ja_tem_leads(): void
    {
        [$t] = $this->empresa($this->nome);
        $this->assertSame(0, Artisan::call('demo:popular-vendas', ['--tenant' => $this->nome]));
        $leads = $this->conta($t, 'crm_leads');

        $this->assertSame(1, Artisan::call('demo:popular-vendas', ['--tenant' => $this->nome]), 'segunda vez recusa');
        $this->assertSame($leads, $this->conta($t, 'crm_leads'));

        File::delete(storage_path('app/demo-vendas-ids.json'));
        $this->assertSame(1, Artisan::call('demo:popular-vendas', ['--tenant' => $this->nome]), 'empresa com leads recusa');
        $this->assertSame($leads, $this->conta($t, 'crm_leads'), 'nada foi acrescentado');
    }

    public function test_remover_apaga_so_o_que_o_comando_criou(): void
    {
        [$t, $admin] = $this->empresa($this->nome);
        $this->assertSame(0, Artisan::call('demo:popular-vendas', ['--tenant' => $this->nome]));
        $this->assertSame(5, $this->conta($t, 'users'));

        $this->assertSame(0, Artisan::call('demo:popular-vendas', ['--tenant' => $this->nome, '--remover' => true]));

        foreach (['crm_leads', 'crm_metas', 'crm_propostas', 'crm_visitas'] as $tabela) {
            $this->assertSame(0, $this->conta($t, $tabela), $tabela);
        }
        $this->assertSame(1, $this->conta($t, 'users'), 'só o administrador que já existia continua');
        $this->assertNotNull(User::withoutGlobalScopes()->find($admin->id));
    }
}
