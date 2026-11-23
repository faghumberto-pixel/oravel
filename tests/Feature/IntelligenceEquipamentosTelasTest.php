<?php

namespace Tests\Feature;

use App\Domain\Intelligence\Models\IntelligenceEquipamento;
use App\Domain\Intelligence\Services\EquipamentosService;
use App\Filament\Pages\IntelligenceDashboard;
use App\Filament\Pages\IntelligenceEquipamento as PaginaEquipamento;
use App\Filament\Pages\IntelligenceEquipamentos;
use App\Models\AssistenteRelatorio;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Demo\IntelligenceDemoService;
use App\Support\ExcelImport\XlsxReader;
use App\Support\MenuModules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class IntelligenceEquipamentosTelasTest extends TestCase
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

    private function outroCliente(array $features = []): User
    {
        $plano = Plan::create(['name' => 'Outro '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $t = Tenant::create(['name' => 'Outra Locadora', 'slug' => 'outra-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $u = User::create(['name' => 'Admin', 'email' => 'outra-'.uniqid().'@teste.com', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return $u;
    }

    public function test_a_tela_de_equipamentos_mostra_cartoes_graficos_e_tabela(): void
    {
        $this->entrarComoClienteDemo();

        Livewire::test(IntelligenceEquipamentos::class)->assertOk()
            ->assertSee('Considerar trocar ou vender')->assertSee('Em risco de parada')->assertSee('Subutilizado')
            ->assertSee('Pareto do custo de manutenção')->assertSee('Valor × receita')->assertSee('Todos os equipamentos')
            ->assertSee('31/10/2026')->assertSee('122 equipamentos analisados')->assertSee('Mostrando 50 de 122')->assertSee('Mostrar mais 50');
    }

    public function test_filtros_busca_ordem_e_mostrar_mais(): void
    {
        $this->entrarComoClienteDemo();

        $t = Livewire::test(IntelligenceEquipamentos::class);
        // mais urgentes primeiro: os "trocar ou vender" abrem a lista
        $t->assertSeeInOrder(['Considerar trocar ou vender', 'EMP-001']);

        // (o nome do equipamento também aparece nos dados dos gráficos: as checagens da tabela usam o link de cada linha)
        $t->set('classificacao', 'trocar_vender')->assertSee('patrimonio=EMP-001', false)->assertDontSee('patrimonio=EMP-040', false)->assertSee('de 10');
        $t->set('classificacao', '')->set('busca', 'emp-040')->assertSee('patrimonio=EMP-040', false)->assertDontSee('patrimonio=EMP-001', false)->assertSee('Mostrando 1 de 1');
        $t->set('busca', '')->set('categoria', 'Gerador')->assertSee('patrimonio=EMP-002', false)->assertDontSee('patrimonio=EMP-001', false);
        $t->set('categoria', '')->call('mostrarMais')->assertSee('Mostrando 100 de 122')->call('mostrarMais')->assertSee('Mostrando 122 de 122')->assertDontSee('Mostrar mais 50');

        // clicar no cartão filtra; clicar de novo limpa
        $t->call('filtrarCard', 'risco_parada')->assertSet('classificacao', 'risco_parada')->call('filtrarCard', 'risco_parada')->assertSet('classificacao', '');
        $t->call('filtrarCard', 'inventada')->assertSet('classificacao', '');
        $t->set('busca', 'zzz')->assertSee('Nenhum equipamento com esse filtro');
    }

    public function test_o_detalhe_mostra_o_motivo_as_acoes_e_a_evolucao(): void
    {
        $this->entrarComoClienteDemo();

        Livewire::withQueryParams(['patrimonio' => 'emp-001'])->test(PaginaEquipamento::class)->assertOk()
            ->assertSee('EMP-001')->assertSee('Considerar trocar ou vender')->assertSee('Por que está aqui')->assertSee('do valor do equipamento')
            ->assertSee('O que fazer')->assertSee('Comparar o custo de reformar')->assertSee('Histórico fotografia a fotografia')
            ->assertSee('31/08/2026')->assertSee('Corretivas, preventivas e reincidência')->assertSee('Todos os equipamentos');

        // equipamento que só começou a piorar: tendência escrita
        Livewire::withQueryParams(['patrimonio' => 'EMP-008'])->test(PaginaEquipamento::class)->assertSee('piorando');
        // saudável: sem bloco de ações
        Livewire::withQueryParams(['patrimonio' => 'EMP-040'])->test(PaginaEquipamento::class)->assertSee('Saudável')->assertDontSee('O que fazer');
    }

    public function test_sem_equipamentos_a_tela_explica_o_que_o_cliente_ganha(): void
    {
        $u = $this->entrarComoClienteDemo();
        \DB::table('intelligence_equipamento_analises')->where('tenant_id', $u->tenant_id)->delete();
        \DB::table('intelligence_equipamentos')->where('tenant_id', $u->tenant_id)->delete();

        Livewire::test(IntelligenceEquipamentos::class)->assertOk()->assertSee('Quais equipamentos explicam os seus números')->assertSee('Importar a primeira planilha');
    }

    public function test_detalhe_de_outra_empresa_nao_abre_e_o_servico_nao_vaza(): void
    {
        $demo = $this->entrarComoClienteDemo();
        $svc = app(EquipamentosService::class);
        $this->assertNotNull($svc->snapshot($demo->tenant_id));
        $this->assertNotNull($svc->equipamento($demo->tenant_id, 'EMP-001'));

        $outra = $this->outroCliente();
        $this->assertNull($svc->snapshot($outra->tenant_id), 'empresa sem equipamentos não herda o snapshot de outra');
        $this->assertNull($svc->equipamento($outra->tenant_id, 'EMP-001'), 'o mesmo patrimônio de outra empresa não aparece');
        $this->assertSame([], $svc->todas($outra->tenant_id, $svc->snapshot($demo->tenant_id)['id']), 'nem pedindo o snapshot dela');
        $this->assertSame(0, $svc->resumo($outra->tenant_id, $svc->snapshot($demo->tenant_id)['id'])['total']);

        $this->actingAs($this->outroCliente(['tabela_intelligence_equipamentos' => true, 'menu_intelligence_equipamentos' => true]));
        Livewire::withQueryParams(['patrimonio' => 'EMP-001'])->test(PaginaEquipamento::class)->assertNotFound();
    }

    public function test_acesso_exige_as_duas_chaves_do_contrato(): void
    {
        $this->actingAs($this->outroCliente());
        $this->assertFalse(IntelligenceEquipamentos::canAccess());
        $this->assertFalse(PaginaEquipamento::canAccess());

        // só os dados, sem a tela: a lista é barrada pelo menu e o detalhe pela chave da tela
        $this->actingAs($this->outroCliente(['tabela_intelligence_equipamentos' => true]));
        $this->assertTrue(IntelligenceEquipamentos::canAccess());
        $this->assertFalse(PaginaEquipamento::canAccess());

        $this->actingAs($this->outroCliente(['tabela_intelligence_equipamentos' => true, 'menu_intelligence_equipamentos' => true]));
        $this->assertTrue(PaginaEquipamento::canAccess());
    }

    public function test_exporta_o_excel_do_filtro_atual(): void
    {
        $this->entrarComoClienteDemo();

        $c = Livewire::test(IntelligenceEquipamentos::class)->set('classificacao', 'trocar_vender')->call('exportar');
        $c->assertFileDownloaded('equipamentos-intelligence.xlsx');

        $arquivo = tempnam(sys_get_temp_dir(), 'exp').'.xlsx';
        file_put_contents($arquivo, base64_decode($c->effects['download']['content']));
        $linhas = XlsxReader::rowsDaAba($arquivo, 'Equipamentos');
        $this->assertNotNull($linhas);
        $this->assertSame('Nº Patrimônio', $linhas[1][0]);
        $this->assertCount(1 + 10, $linhas, 'cabeçalho + os 10 "trocar ou vender"');
        $this->assertSame('Considerar trocar ou vender', $linhas[2][3]);
        $this->assertStringContainsString('do valor do equipamento', $linhas[2][4]);
    }

    public function test_relatorio_de_equipamentos_para_whatsapp_e_impressao(): void
    {
        $u = $this->entrarComoClienteDemo();
        $base = IntelligenceDashboard::getUrl().'/relatorio';

        $r = $this->get("{$base}/ver?tipo=equipamentos");
        $r->assertRedirect();
        $pagina = $this->get($r->headers->get('Location'))->assertOk();
        $pagina->assertSee('Intelligence — equipamentos em 31/10/2026', false)->assertSee('Considerar trocar ou vender')->assertSee('Imprimir / salvar PDF');

        $rel = AssistenteRelatorio::withoutGlobalScopes()->where('tenant_id', $u->tenant_id)->where('titulo', 'like', '%equipamentos em%')->firstOrFail();
        $this->assertContains('Situação', $rel->colunas);
        $this->assertStringContainsString('para considerar trocar ou vender', $rel->resumo);
        $this->assertGreaterThan(10, $rel->total_linhas);
        $semSaudaveis = collect($rel->linhas)->every(fn ($l) => ! in_array('Saudável', $l, true));
        $this->assertTrue($semSaudaveis, 'o relatório é a lista de decisão: só o que não está saudável');

        $zap = $this->get("{$base}/zap?tipo=equipamentos");
        $this->assertStringStartsWith('https://wa.me/?text=', $zap->headers->get('Location'));

        // sem equipamentos analisados: 404 em vez de relatório vazio
        $outra = $this->outroCliente(['tabela_intelligence_snapshots' => true, 'menu_intelligence_dashboard' => true]);
        $this->flushSession(); // trocar de usuário na mesma sessão derruba o login (conferência de senha da sessão do painel)
        $this->actingAs($outra);
        $this->get("{$base}/ver?tipo=equipamentos")->assertNotFound();
    }

    public function test_migration_liga_a_tela_so_nos_contratos_do_intelligence(): void
    {
        $mk = fn (string $nome, ?string $pacote, ?array $pacotes, array $f) => Plan::create(['name' => $nome, 'pacote' => $pacote, 'pacotes' => $pacotes, 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $f]);
        $int = $mk('Intelligence', 'intelligence', null, ['tabela_intelligence_snapshots' => true]);
        $combo = $mk('ERP + Intelligence', 'locadora', ['intelligence'], ['tabela_clients']);
        $erp = $mk('Só ERP', 'locadora', null, ['tabela_clients' => true]);

        (require database_path('migrations/2027_01_15_100000_backfill_tela_equipamentos_do_intelligence.php'))->up();

        $this->assertTrue($int->fresh()->hasFeature('menu_intelligence_equipamentos'));
        $this->assertTrue($combo->fresh()->hasFeature('menu_intelligence_equipamentos'));
        $this->assertTrue($combo->fresh()->hasFeature('tabela_clients'), 'lista virou mapa sem perder nada');
        $this->assertFalse($erp->fresh()->hasFeature('menu_intelligence_equipamentos'));
    }

    public function test_o_menu_conhece_a_tela_e_os_equipamentos_do_snapshot_sao_imutaveis(): void
    {
        $u = $this->entrarComoClienteDemo();

        $this->assertSame('menu_intelligence_equipamentos', MenuModules::keyForSlug('intelligence-equipamentos'));
        $this->assertSame('menu_intelligence_equipamentos', MenuModules::keyForItem('Intelligence', 'Equipamentos'));

        $this->expectException(\LogicException::class);
        IntelligenceEquipamento::withoutGlobalScopes()->where('tenant_id', $u->tenant_id)->firstOrFail()->delete();
    }
}
