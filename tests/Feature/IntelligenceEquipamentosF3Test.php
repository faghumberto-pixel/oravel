<?php

namespace Tests\Feature;

use App\Domain\Intelligence\Services\EquipamentosService;
use App\Domain\Intelligence\Services\InsightsDeEquipamentos;
use App\Filament\Pages\IntelligenceComparacao;
use App\Filament\Pages\IntelligenceDashboard;
use App\Filament\Pages\IntelligenceDiagnostico;
use App\Filament\Pages\IntelligencePlano;
use App\Models\AssistenteRelatorio;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Demo\IntelligenceDemoService;
use App\Services\IntelligenceChatService;
use App\Support\Assistente\TenantSql;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class IntelligenceEquipamentosF3Test extends TestCase
{
    use DatabaseTransactions;

    private function entrarComoClienteDemo(): User
    {
        app(IntelligenceDemoService::class)->criar('Senha12345');
        $u = User::withoutGlobalScopes()->where('email', IntelligenceDemoService::EMAIL)->firstOrFail();
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        config(['oravel.require_2fa' => false]);

        return $u;
    }

    private function snapshots(string $tenantId): array
    {
        return \DB::table('intelligence_snapshots')->where('tenant_id', $tenantId)->orderBy('data_referencia')->pluck('id', 'data_referencia')->mapWithKeys(fn ($id, $d) => [substr($d, 0, 10) => $id])->all();
    }

    public function test_achados_por_equipamento_vem_com_nomes_e_ordenados_por_gravidade(): void
    {
        $u = $this->entrarComoClienteDemo();
        $snap = app(EquipamentosService::class)->snapshot($u->tenant_id);
        $achados = collect(app(InsightsDeEquipamentos::class)->do_snapshot($u->tenant_id, $snap['id']))->keyBy('chave');

        $troca = $achados['trocar_vender'];
        $this->assertSame('critico', $troca['gravidade']);
        $classes = \DB::table('intelligence_equipamento_analises')->where('snapshot_id', $snap['id'])->whereIn('patrimonio', $troca['patrimonios'])->pluck('classificacao')->unique()->all();
        $this->assertSame(['trocar_vender'], $classes, 'todos os citados estão mesmo em trocar ou vender');
        $this->assertCount(5, $troca['patrimonios']);
        $this->assertSame(5, $troca['mais'], '10 no total, 5 citados e mais 5');
        $this->assertStringContainsString('10 equipamentos já custam mais de 60% do valor', $troca['texto']);
        $this->assertStringContainsString('e mais 5', $troca['texto']);
        $this->assertStringStartsWith('Avaliar a venda ou a reforma dos patrimônios ', $troca['acao']);
        $this->assertSame([30, 'Alta'], [$troca['prazo'], $troca['prioridade']]);

        $this->assertSame('atencao', $achados['risco_parada']['gravidade']);
        $this->assertStringStartsWith('Priorizar na oficina os patrimônios ', $achados['risco_parada']['acao']);
        $this->assertStringStartsWith('Ofertar ou vender os patrimônios ', $achados['subutilizado']['acao']);
        $this->assertSame([60, 'Média'], [$achados['revisar']['prazo'], $achados['revisar']['prioridade']]);
        $this->assertArrayHasKey('pareto', $achados->all());
        $this->assertNull($achados['pareto']['acao'], 'concentração de custo é informação, não ação');

        $gravidades = $achados->pluck('gravidade')->values()->all();
        $ordem = ['critico' => 0, 'atencao' => 1, 'info' => 2];
        $ordenado = collect($gravidades)->map(fn ($g) => $ordem[$g])->all();
        $sorted = $ordenado;
        sort($sorted);
        $this->assertSame($sorted, $ordenado, 'críticos primeiro, informação por último');
        $this->assertSame('critico', $gravidades[0]);
    }

    public function test_redacao_dos_nomes_e_do_singular(): void
    {
        $i = new InsightsDeEquipamentos;
        $this->assertSame('A', $i->nomes(['A'], 0));
        $this->assertSame('A e B', $i->nomes(['A', 'B'], 0));
        $this->assertSame('A, B e C', $i->nomes(['A', 'B', 'C'], 0));
        $this->assertSame('A, B e mais 7', $i->nomes(['A', 'B'], 7));
    }

    public function test_diagnostico_mostra_os_equipamentos_por_tras_dos_problemas(): void
    {
        $this->entrarComoClienteDemo();

        Livewire::test(IntelligenceDiagnostico::class)->assertOk()
            ->assertSee('Equipamentos que explicam os números')->assertSee('Equipamentos para decidir: trocar ou vender')
            ->assertSee('patrimonio=EMP-001', false)->assertSee('classificacao=trocar_vender', false)->assertSee('Equipamentos parados sem contrato');
    }

    public function test_sem_a_chave_da_tela_os_achados_nao_aparecem(): void
    {
        $u = $this->entrarComoClienteDemo();
        $plano = Plan::find(Tenant::withoutGlobalScopes()->find($u->tenant_id)->plan_id);
        $f = $plano->features;
        $f['menu_intelligence_equipamentos'] = false;
        $plano->update(['features' => $f]);

        Livewire::test(IntelligenceDiagnostico::class)->assertOk()->assertDontSee('Equipamentos que explicam os números');
        Livewire::test(IntelligencePlano::class)->assertOk()->assertDontSee('Avaliar a venda ou a reforma');
    }

    public function test_plano_traz_acoes_com_nomes_nos_prazos(): void
    {
        $this->entrarComoClienteDemo();

        Livewire::test(IntelligencePlano::class)->assertOk()
            ->assertSee('Avaliar a venda ou a reforma dos patrimônios')->assertSee('Priorizar na oficina os patrimônios')
            ->assertSee('Equipamentos')->assertSee('patrimonio=EMP-001', false);
    }

    public function test_relatorios_de_diagnostico_e_plano_incluem_as_linhas_por_equipamento(): void
    {
        $u = $this->entrarComoClienteDemo();
        $base = IntelligenceDashboard::getUrl().'/relatorio';

        foreach (['diagnostico', 'plano'] as $tipo) {
            $this->get("{$base}/ver?tipo={$tipo}")->assertRedirect();
        }
        $diag = AssistenteRelatorio::withoutGlobalScopes()->where('tenant_id', $u->tenant_id)->where('titulo', 'like', '%diagnóstico em%')->firstOrFail();
        $this->assertTrue(collect($diag->linhas)->contains(fn ($l) => str_contains(implode(' ', $l), 'Equipamentos para decidir: trocar ou vender')));
        $plano = AssistenteRelatorio::withoutGlobalScopes()->where('tenant_id', $u->tenant_id)->where('titulo', 'like', '%plano de evolução em%')->firstOrFail();
        $this->assertTrue(collect($plano->linhas)->contains(fn ($l) => str_contains(implode(' ', $l), 'Avaliar a venda ou a reforma dos patrimônios')));
        $this->assertStringContainsString('por equipamento', $plano->resumo);
    }

    public function test_comparacao_mostra_quem_mudou_de_situacao_entre_duas_fotografias(): void
    {
        $u = $this->entrarComoClienteDemo();
        $s = $this->snapshots($u->tenant_id);
        $svc = app(EquipamentosService::class);

        $m = $svc->mudancas($u->tenant_id, $s['2026-08-31'], $s['2026-10-31']);
        $this->assertGreaterThanOrEqual(4, $m['pioraram']);
        $this->assertGreaterThanOrEqual(1, $m['melhoraram']);
        $emp8 = collect($m['mudaram'])->firstWhere('patrimonio', 'EMP-008');
        $this->assertSame(['revisar', 'trocar_vender', 'pior'], [$emp8['de'], $emp8['para'], $emp8['sentido']]);
        $this->assertSame(['EMP-121', 'EMP-122'], array_slice($m['novos'], -2), 'outubro tem 2 equipamentos a mais que agosto');
        $this->assertSame([], $m['sairam']);
        $this->assertSame('pior', $m['mudaram'][0]['sentido'], 'quem piorou mais vem primeiro');

        Livewire::test(IntelligenceComparacao::class)->set('de', $s['2026-08-31'])->set('para', $s['2026-10-31'])
            ->assertSee('Equipamentos que mudaram de situação')->assertSee('pioraram')->assertSee('patrimonio=EMP-008', false);

        // o mesmo snapshot dos dois lados ou de outra empresa: nada
        $this->assertNull($svc->mudancas($u->tenant_id, $s['2026-08-31'], '00000000-0000-0000-0000-000000000000'));
        $plano = Plan::create(['name' => 'Outro', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $outro = Tenant::create(['name' => 'Outra', 'slug' => 'outra-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $this->assertNull($svc->mudancas($outro->id, $s['2026-08-31'], $s['2026-10-31']), 'snapshots de outra empresa não são lidos');
    }

    public function test_assistente_enxerga_os_equipamentos_so_da_propria_empresa(): void
    {
        $u = $this->entrarComoClienteDemo();
        $tabelas = IntelligenceChatService::TABELAS;

        [$linhas, $erro] = TenantSql::run($u->tenant_id, 'SELECT COUNT(*) AS n FROM intelligence_equipamentos', $tabelas);
        $this->assertNull($erro, (string) $erro);
        $this->assertSame(362, (int) $linhas[0]['n'], '120 + 120 + 122 equipamentos nos 3 meses');

        [$linhas, $erro] = TenantSql::run($u->tenant_id, "SELECT e.patrimonio, a.classificacao, a.motivo FROM intelligence_equipamentos e JOIN intelligence_equipamento_analises a ON a.equipamento_id = e.id JOIN intelligence_snapshots s ON s.id = e.snapshot_id WHERE a.classificacao = 'trocar_vender' ORDER BY s.data_referencia DESC, e.patrimonio LIMIT 3", $tabelas);
        $this->assertNull($erro, (string) $erro);
        $this->assertCount(3, $linhas);
        $this->assertStringContainsString('do valor do equipamento', $linhas[0]['motivo']);

        $plano = Plan::create(['name' => 'Outro', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $outro = Tenant::create(['name' => 'Outra', 'slug' => 'outra-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        [$vazio] = TenantSql::run($outro->id, 'SELECT COUNT(*) AS n FROM intelligence_equipamentos', $tabelas);
        $this->assertSame(0, (int) $vazio[0]['n'], 'outra empresa nunca vê os equipamentos desta');
    }

    public function test_resumo_e_instrucoes_do_assistente_falam_de_equipamentos(): void
    {
        $u = $this->entrarComoClienteDemo();
        $svc = app(IntelligenceChatService::class)->paraTenant($u->tenant_id);

        $contexto = (new \ReflectionMethod($svc, 'montarContexto'))->invoke($svc);
        $this->assertStringContainsString('por_classificacao', $contexto);
        $this->assertStringContainsString('trocar_vender', $contexto);
        $this->assertStringContainsString('10 equipamentos já custam', $contexto);
        $this->assertStringContainsString('2026-10-31', $contexto);

        $prompt = (new \ReflectionMethod($svc, 'systemPrompt'))->invoke($svc);
        $this->assertStringContainsString('intelligence_equipamento_analises', $prompt);
        $this->assertStringContainsString('patrimônio e nome', $prompt);
        $this->assertStringContainsString('nunca como ordem', $prompt);
        $this->assertStringNotContainsString('maintenance_orders', $prompt);
    }
}
