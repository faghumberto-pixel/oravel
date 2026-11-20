<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebEvent;
use App\Models\WebPageview;
use App\Models\WebVisit;
use App\Relacionamento\Filament\Pages\DashboardOrg;
use App\Relacionamento\Filament\Widgets\OrgClicksTable;
use App\Relacionamento\Filament\Widgets\OrgFerramentasTable;
use App\Relacionamento\Filament\Widgets\OrgRelacionamentoStats;
use App\Relacionamento\Filament\Widgets\OrgStatsOverview;
use App\Relacionamento\Filament\Widgets\OrgTopPagesTable;
use App\Relacionamento\Models\RelContato;
use App\Relacionamento\Models\RelInteracao;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Painel das visitas ao oravel.org e seus filtros (periodo, dispositivo, estado, origem, retorno). DatabaseTransactions. */
class SiteOrgPainelTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        config(['oravel.super_admins' => ['equipe@oravel.test']]);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        return User::factory()->create(['email' => 'equipe@oravel.test']);
    }

    private function visit(string $site, string $s, array $extra = []): WebVisit
    {
        return WebVisit::create($extra + [
            'site' => $site, 'visitor_token' => 'v-'.$s, 'session_token' => $s, 'ip_address' => '201.27.224.131',
            'device_type' => 'desktop', 'state' => 'SP', 'is_returning' => false, 'started_at' => now(), 'last_activity_at' => now(),
            'page_views' => 1, 'duration_seconds' => 30, 'landing_path' => '/',
        ]);
    }

    /** Valor do 1º cartao (visitantes unicos) do bloco de estatisticas, com os filtros dados. */
    private function unicos(array $filters): int
    {
        $w = new OrgStatsOverview;
        $w->filters = ['period' => '7'] + $filters;

        return (fn () => $this->getStats())->call($w)[0]->getValue();
    }

    private function cenario(): void
    {
        $this->visit('oravel.org', 's-a', ['device_type' => 'mobile', 'state' => 'SP', 'referrer_host' => 'google.com']);
        $this->visit('oravel.org', 's-b', ['device_type' => 'desktop', 'state' => 'RJ', 'is_returning' => true, 'utm_source' => 'linkedin']);
        $this->visit('oravel.org', 's-c', ['device_type' => 'desktop', 'state' => 'SP']);
        $this->visit('oravel.com.br', 's-com', ['device_type' => 'mobile', 'state' => 'MG', 'referrer_host' => 'google.com']);
    }

    public function test_so_super_admin_acessa_o_painel_e_ele_renderiza(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'comum@x.test']));
        $this->assertFalse(DashboardOrg::canAccess());

        Livewire::actingAs($this->admin())->test(DashboardOrg::class)->assertSuccessful()->assertSee('aceitou o aviso de privacidade');
    }

    public function test_sem_filtro_conta_so_o_oravel_org(): void
    {
        $this->cenario();

        $this->assertSame(3, $this->unicos([]));
    }

    public function test_filtro_de_dispositivo(): void
    {
        $this->cenario();

        $this->assertSame(1, $this->unicos(['device' => 'mobile']));
        $this->assertSame(2, $this->unicos(['device' => 'desktop']));
    }

    public function test_filtro_de_estado(): void
    {
        $this->cenario();

        $this->assertSame(2, $this->unicos(['uf' => 'SP']));
        $this->assertSame(1, $this->unicos(['uf' => 'RJ']));
        $this->assertSame(0, $this->unicos(['uf' => 'MG']), 'MG so existe no institucional');
    }

    public function test_filtro_de_origem_por_referencia_utm_e_direto(): void
    {
        $this->cenario();

        $this->assertSame(1, $this->unicos(['origem' => 'google.com']));
        $this->assertSame(1, $this->unicos(['origem' => 'linkedin']));
        $this->assertSame(1, $this->unicos(['origem' => 'direto']));
    }

    public function test_filtro_de_visitantes_novos_e_que_voltaram(): void
    {
        $this->cenario();

        $this->assertSame(1, $this->unicos(['retorno' => 'voltou']));
        $this->assertSame(2, $this->unicos(['retorno' => 'novo']));
    }

    public function test_filtros_se_combinam(): void
    {
        $this->cenario();

        $this->assertSame(1, $this->unicos(['device' => 'desktop', 'uf' => 'SP']));
        $this->assertSame(0, $this->unicos(['device' => 'mobile', 'uf' => 'RJ']));
    }

    public function test_paginas_e_cliques_respeitam_site_e_filtros(): void
    {
        $this->cenario();
        foreach (['s-a' => '/ferramentas/mobile/', 's-b' => '/ferramentas/desktop/', 's-com' => '/so-no-institucional/'] as $s => $path) {
            WebPageview::create(['web_visit_id' => WebVisit::where('session_token', $s)->value('id'), 'page_token' => 'pg-'.$s, 'path' => $path, 'entered_at' => now()]);
            WebEvent::create(['web_visit_id' => WebVisit::where('session_token', $s)->value('id'), 'type' => 'click', 'label' => 'Clique '.$path, 'path' => $path, 'occurred_at' => now()]);
        }
        $u = $this->admin();

        Livewire::actingAs($u)->test(OrgTopPagesTable::class, ['filters' => ['period' => '7']])
            ->assertSee('/ferramentas/mobile/')->assertSee('/ferramentas/desktop/')->assertDontSee('/so-no-institucional/');
        Livewire::actingAs($u)->test(OrgTopPagesTable::class, ['filters' => ['period' => '7', 'device' => 'mobile']])
            ->assertSee('/ferramentas/mobile/')->assertDontSee('/ferramentas/desktop/');
        Livewire::actingAs($u)->test(OrgClicksTable::class, ['filters' => ['period' => '7', 'device' => 'desktop']])
            ->assertSee('Clique /ferramentas/desktop/')->assertDontSee('Clique /ferramentas/mobile/')->assertDontSee('Clique /so-no-institucional/');
    }

    public function test_ferramentas_concluidas_agrupa_e_respeita_o_periodo(): void
    {
        $c = RelContato::create(['email' => 'p@x.com']);
        foreach ([1, 2, 3] as $d) {
            RelInteracao::create(['tipo' => 'ferramenta', 'conteudo_slug' => 'ferramentas/preco-diaria', 'titulo' => 'Calculadora de Preço da Diária', 'duracao_segundos' => 120, 'contato_id' => $d === 1 ? $c->id : null, 'ocorrido_em' => now()->subDays($d)]);
        }
        RelInteracao::create(['tipo' => 'ferramenta', 'conteudo_slug' => 'ferramentas/antiga', 'titulo' => 'Ferramenta Antiga', 'ocorrido_em' => now()->subDays(60)]);
        RelInteracao::create(['tipo' => 'artigo', 'conteudo_slug' => 'a1', 'titulo' => 'Um Artigo', 'ocorrido_em' => now()]);

        Livewire::actingAs($this->admin())->test(OrgFerramentasTable::class, ['filters' => ['period' => '7']])
            ->assertSee('Calculadora de Preço da Diária')->assertDontSee('Ferramenta Antiga')->assertDontSee('Um Artigo');
    }

    public function test_estatisticas_de_pessoas_descontam_descadastrados(): void
    {
        RelContato::create(['email' => 'a@x.com', 'consentimento_em' => now()]);
        RelContato::create(['email' => 'b@x.com', 'consentimento_em' => now(), 'descadastrado_em' => now()]);
        RelContato::create(['email' => 'c@x.com']);

        $w = new OrgRelacionamentoStats;
        $w->filters = ['period' => '7'];
        $stats = (fn () => $this->getStats())->call($w);

        $this->assertSame(3, $stats[0]->getValue());
        $this->assertSame(1, $stats[1]->getValue(), 'aceitaram receber: so quem tem aceite e nao saiu');
        $this->assertSame(1, $stats[2]->getValue());
    }

    public function test_pagina_e_blocos_do_painel_estao_registrados_no_painel_da_central(): void
    {
        // Widget fora de ->widgets() = ComponentNotFoundException disfarcada de "page expired"/419 (ver SiteInstitucionalPanelTest).
        $panel = Filament::getPanel('central');
        $this->assertContains(DashboardOrg::class, $panel->getPages(), 'DashboardOrg precisa estar em CentralPanelProvider::pages()');

        foreach ((new DashboardOrg)->getWidgets() as $widget) {
            $this->assertContains($widget, $panel->getWidgets(), "{$widget} precisa estar registrado em CentralPanelProvider::widgets()");
        }
    }
}
