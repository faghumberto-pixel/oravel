<?php

namespace Tests\Feature;

use App\Filament\Pages\CustoPorEquipamento;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustoManutencaoService;
use App\Services\Demo\LocadoraDemoService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Tela "Custo de Manutencao" (Inteligencia de Ativos): ranking por equipamento, opcional por contrato. */
class CustoPorEquipamentoPaginaTest extends TestCase
{
    use DatabaseTransactions;

    private function entrarNaDemo(): void
    {
        app(LocadoraDemoService::class)->criar('x');
        $this->actingAs(User::withoutGlobalScopes()->where('email', LocadoraDemoService::EMAIL_ADMIN)->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_demo_mostra_ranking_com_os_4_mais_caros_primeiro_e_cards_filtram(): void
    {
        $this->entrarNaDemo();

        $pagina = Livewire::test(CustoPorEquipamento::class)->set('meses', 18)->assertSuccessful()
            ->assertSee('Custo de manutenção por equipamento')->assertSee('Considerar trocar ou vender');

        $linhas = $pagina->instance()->dados()['linhas'];
        $this->assertCount(25, $linhas);
        $this->assertSame(['C-02', 'G-03', 'PA-02', 'PT-02'], $linhas->take(4)->pluck('patrimonio')->sort()->values()->all());
        $this->assertSame('trocar_vender', $linhas->firstWhere('patrimonio', 'C-02')['situacao']['chave']);
        $this->assertCount(12, $linhas->first()['serie']);

        $cards = collect($pagina->instance()->dados()['cards'])->keyBy('chave');
        $this->assertSame(25, $cards['total']['quantidade']);
        $this->assertSame(3, $cards['trocar_vender']['quantidade']);
        $this->assertSame(1, $cards['revisar']['quantidade']);
        $this->assertSame(21, $cards['ok']['quantidade']);

        $pagina->call('filtrarCard', 'trocar_vender');
        $filtradas = $pagina->instance()->dados();
        $this->assertSame(['C-02', 'G-03', 'PT-02'], $filtradas['linhas']->pluck('patrimonio')->sort()->values()->all());
        $this->assertTrue(collect($filtradas['cards'])->firstWhere('chave', 'trocar_vender')['ativo']);

        $pagina->call('filtrarCard', 'total')->set('busca', 'Gerador')->set('ordem', 'nome');
        $nomes = $pagina->instance()->dados()['linhas']->pluck('nome');
        $this->assertCount(5, $nomes);
        $this->assertSame($nomes->sort()->values()->all(), $nomes->values()->all());

        $pagina->set('busca', '')->set('categoria', 'Compressor');
        $this->assertCount(4, $pagina->instance()->dados()['linhas']);
    }

    public function test_periodo_menor_reduz_o_custo_do_periodo_sem_mudar_o_acumulado(): void
    {
        $this->entrarNaDemo();
        $servico = app(CustoManutencaoService::class);

        $seis = $servico->ranking(6)->firstWhere('patrimonio', 'G-03');
        $vinteEQuatro = $servico->ranking(24)->firstWhere('patrimonio', 'G-03');

        $this->assertLessThan($vinteEQuatro['custo_periodo'], $seis['custo_periodo']);
        $this->assertSame($seis['custo_total'], $vinteEQuatro['custo_total']);
        $this->assertEqualsWithDelta($vinteEQuatro['custo_total'], $vinteEQuatro['custo_periodo'], 0.01, '24 meses cobre todo o historico da demo');
    }

    public function test_so_acessa_quem_tem_a_chave_no_contrato_e_a_url_responde_403_sem_ela(): void
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'cp-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'A', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertFalse(CustoPorEquipamento::canAccess());
        $this->get('/admin/custo-manutencao-equipamentos')->assertForbidden();

        $plan->update(['features' => ['tabela_assets', CustoManutencaoService::FEATURE]]);
        auth()->user()->unsetRelation('tenant');

        $this->assertTrue(CustoPorEquipamento::canAccess());
        $this->get('/admin/custo-manutencao-equipamentos')->assertOk()->assertSee('Custo de manutenção por equipamento');
    }
}
