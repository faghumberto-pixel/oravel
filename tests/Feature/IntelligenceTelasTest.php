<?php

namespace Tests\Feature;

use App\Domain\Intelligence\Models\IntelligenceSnapshot;
use App\Domain\Intelligence\Services\EvolucaoService;
use App\Filament\Pages\IntelligenceAssistente;
use App\Filament\Pages\IntelligenceComparacao;
use App\Filament\Pages\IntelligenceDashboard;
use App\Filament\Pages\IntelligenceDiagnostico;
use App\Filament\Pages\IntelligenceOperacao;
use App\Filament\Pages\IntelligencePlano;
use App\Livewire\Assistente\IntelligenceChat;
use App\Models\AssistenteRelatorio;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Demo\IntelligenceDemoService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class IntelligenceTelasTest extends TestCase
{
    use DatabaseTransactions;

    private function entrarComoClienteDemo(): User
    {
        app(IntelligenceDemoService::class)->criar('Senha12345');
        $u = User::withoutGlobalScopes()->where('email', IntelligenceDemoService::EMAIL)->firstOrFail();
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        config(['oravel.require_2fa' => false]); // as rotas de relatório passam pelo mesmo middleware do painel

        return $u;
    }

    private function clienteSemPacote(): User
    {
        $plano = Plan::create(['name' => 'Sem Intelligence', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $t = Tenant::create(['name' => 'Outra Locadora', 'slug' => 'outra-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $u = User::create(['name' => 'Admin', 'email' => 'outra-'.uniqid().'@teste.com', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return $u;
    }

    public function test_cada_entrega_tem_a_sua_tela_e_ela_mostra_o_conteudo_esperado(): void
    {
        $this->entrarComoClienteDemo();

        Livewire::test(IntelligenceDashboard::class)->assertOk()
            ->assertSee('Índice geral')->assertSee('Receita do mês')->assertSee('Mapa de calor')->assertSee('Evolução do índice')->assertSee('Pergunte ao assistente');
        Livewire::test(IntelligenceDiagnostico::class)->assertOk()
            ->assertSee('Principais pontos de atenção')->assertSee('Mapa de prioridades')->assertSee('Pontos fortes')->assertSee('Por que importa');
        Livewire::test(IntelligenceComparacao::class)->assertOk()
            ->assertSee('Radar das áreas')->assertSee('Todos os indicadores')->assertSee('31/10/2026')->assertSee('30/09/2026');
        Livewire::test(IntelligencePlano::class)->assertOk()
            ->assertSee('Foco do ciclo')->assertSee('Primeiros 30 dias')->assertSee('meta do ciclo');
        Livewire::test(IntelligenceOperacao::class)->assertOk()
            ->assertSee('Frota hoje')->assertSee('Receita, custos e resultado')->assertSee('Corretivas × preventivas');
        Livewire::test(IntelligenceAssistente::class)->assertOk()->assertSee('Converse com os seus números')->assertSee('Minha disponibilidade aumentou?');
    }

    public function test_sem_importacao_cada_tela_mostra_o_que_o_cliente_vai_ganhar(): void
    {
        $this->entrarComoClienteDemo();
        IntelligenceSnapshot::withoutGlobalScopes()->getConnection()->table('intelligence_diagnostics')->delete();
        IntelligenceSnapshot::withoutGlobalScopes()->getConnection()->table('intelligence_indicators')->delete();
        IntelligenceSnapshot::withoutGlobalScopes()->getConnection()->table('intelligence_snapshots')->delete();

        Livewire::test(IntelligenceDashboard::class)->assertSee('O Índice Oravel de Saúde da sua locadora')->assertSee('Importar a primeira planilha');
        Livewire::test(IntelligenceDiagnostico::class)->assertSee('Onde a sua locadora precisa de atenção');
        Livewire::test(IntelligenceComparacao::class)->assertSee('A sua locadora contra ela mesma');
        Livewire::test(IntelligencePlano::class)->assertSee('O caminho para uma locadora mais saudável');
        Livewire::test(IntelligenceOperacao::class)->assertSee('A operação da sua locadora em um só lugar');
    }

    public function test_acesso_exige_o_pacote_no_contrato(): void
    {
        $this->actingAs($this->clienteSemPacote());

        foreach ([IntelligenceDashboard::class, IntelligenceDiagnostico::class, IntelligenceComparacao::class, IntelligencePlano::class, IntelligenceOperacao::class, IntelligenceAssistente::class] as $pagina) {
            $this->assertFalse($pagina::canAccess(), $pagina);
        }
    }

    public function test_comparacao_interna_so_aceita_fotografias_da_propria_empresa(): void
    {
        $u = $this->entrarComoClienteDemo();
        $servico = app(EvolucaoService::class);
        $ids = array_keys($servico->opcoes($u->tenant_id));
        $this->assertCount(3, $ids);

        $c = $servico->comparar($u->tenant_id, $ids[2], $ids[0]);
        $this->assertSame('2026-08-31', $c['de']);
        $this->assertSame('2026-10-31', $c['para']);
        $frota = collect($c['areas'])->firstWhere('chave', 'frota');
        $this->assertGreaterThan(0, $frota['delta'], 'a frota melhorou de agosto para outubro');
        $inad = collect($c['indicadores'])->firstWhere('chave', 'inadimplencia_pct');
        $this->assertSame('pior', $inad['sentido'], 'a inadimplência piorou');

        // o id de OUTRA empresa nunca é aceito
        $outra = $this->clienteSemPacote();
        $this->assertNull($servico->comparar($outra->tenant_id, $ids[2], $ids[0]));
        $this->assertSame([], $servico->opcoes($outra->tenant_id));

        $tela = Livewire::test(IntelligenceComparacao::class)->assertSet('para', $ids[0])->assertSet('de', $ids[1]);
        $tela->set('de', $ids[2])->assertSee('Inadimplência');
        $tela->set('de', $ids[0])->assertSee('Escolha duas fotografias diferentes');
    }

    public function test_botoes_de_relatorio_geram_a_pagina_minima_de_impressao_e_o_link_do_whatsapp(): void
    {
        $u = $this->entrarComoClienteDemo();
        $base = IntelligenceDashboard::getUrl().'/relatorio';

        foreach (['painel' => 'saúde da empresa', 'diagnostico' => 'diagnóstico', 'plano' => 'plano de evolução'] as $tipo => $trecho) {
            $r = $this->get("{$base}/ver?tipo={$tipo}");
            $r->assertRedirect();
            $url = $r->headers->get('Location');
            $this->assertStringContainsString('/assistente/r/', $url);

            $pagina = $this->get($url)->assertOk();
            $pagina->assertSee('Intelligence — '.$trecho, false)->assertSee('Imprimir / salvar PDF')->assertSee('Compartilhar no WhatsApp');
            $this->assertStringContainsString('noindex', (string) $pagina->headers->get('X-Robots-Tag'));
        }

        $rel = AssistenteRelatorio::withoutGlobalScopes()->where('tenant_id', $u->tenant_id)->where('titulo', 'like', '%plano de evolução%')->firstOrFail();
        $this->assertSame($u->tenant_id, $rel->tenant_id);
        $this->assertSame('intelligence', $rel->area);
        $this->assertContains('Prazo', $rel->colunas);
        $this->assertGreaterThan(0, $rel->total_linhas);
        $this->assertTrue($rel->expires_at->isFuture());

        $zap = $this->get("{$base}/zap?tipo=plano");
        $zap->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/?text=', $zap->headers->get('Location'));
        $this->assertStringContainsString(rawurlencode('/assistente/r/'), $zap->headers->get('Location'));

        // clicar de novo na mesma foto reaproveita o relatório (não enche a tabela)
        $antes = AssistenteRelatorio::withoutGlobalScopes()->where('tenant_id', $u->tenant_id)->count();
        $this->get("{$base}/ver?tipo=plano");
        $this->assertSame($antes, AssistenteRelatorio::withoutGlobalScopes()->where('tenant_id', $u->tenant_id)->count());

        $this->get("{$base}/imprimir?tipo=plano")->assertNotFound();
    }

    public function test_relatorio_exige_login_e_pacote(): void
    {
        $this->entrarComoClienteDemo();
        $url = IntelligenceDashboard::getUrl().'/relatorio/ver?tipo=painel';

        $this->actingAs($this->clienteSemPacote());
        config(['oravel.require_2fa' => false]);
        $this->get($url)->assertForbidden();

        auth()->logout();
        $this->get($url)->assertRedirect();
    }

    public function test_chat_embutido_mostra_perguntas_proprias_e_os_outros_modos_continuam_iguais(): void
    {
        $this->entrarComoClienteDemo();

        Livewire::test(IntelligenceChat::class, ['embutido' => true])
            ->assertSet('embutido', true)->assertSet('isOpen', true)
            ->assertSee('Minha disponibilidade aumentou?')->assertSee('Olá! Sou o assistente do Intelligence')
            ->assertDontSee('Quais prospects')->assertDontSee('bg-black/30');

        // celular (tela cheia): sem lista de perguntas rápidas e sem gatilho flutuante, como sempre foi
        Livewire::test(IntelligenceChat::class, ['fullscreen' => true])
            ->assertSet('fullscreen', true)->assertSet('embutido', false)
            ->assertDontSee('Perguntas rápidas')->assertSee('Olá! Sou o assistente do Intelligence');

        // modo painel lateral (Central/Prospects): continua com o gatilho flutuante quando fechado
        Livewire::test(IntelligenceChat::class)->assertSet('isOpen', false)->assertSee('Abrir Intelligence');
    }

    public function test_migration_liga_as_telas_novas_so_nos_contratos_do_intelligence(): void
    {
        $mk = fn (string $nome, ?string $pacote, ?array $pacotes, array $features) => Plan::create(['name' => $nome, 'pacote' => $pacote, 'pacotes' => $pacotes, 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $so = $mk('So Intelligence', 'intelligence', null, ['tabela_intelligence_snapshots' => true]);                 // mapa
        $combo = $mk('ERP + Intelligence', 'locadora', ['intelligence'], ['tabela_clients']);                          // lista
        $erp = $mk('Só ERP', 'locadora', null, ['tabela_clients' => true]);
        $nova = ['menu_intelligence_diagnostico', 'menu_intelligence_comparacao', 'menu_intelligence_plano', 'menu_intelligence_operacao'];

        (require database_path('migrations/2027_01_09_100000_backfill_telas_do_intelligence_nos_contratos.php'))->up();

        foreach ([$so, $combo] as $p) {
            foreach ($nova as $k) {
                $this->assertTrue($p->fresh()->hasFeature($k), "{$p->name} deve ter {$k}");
            }
        }
        $this->assertTrue($combo->fresh()->hasFeature('tabela_clients'), 'não perde o que já tinha (lista virou mapa)');
        $this->assertTrue($so->fresh()->hasFeature('tabela_intelligence_snapshots'));
        foreach ($nova as $k) {
            $this->assertFalse($erp->fresh()->hasFeature($k), 'contrato sem Intelligence não é tocado');
        }
        $this->assertSame(['tabela_clients' => true], $erp->fresh()->features);
    }
}
