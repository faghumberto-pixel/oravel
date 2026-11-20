<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\WebVisitResource\Pages\ListWebVisits;
use App\Filament\Central\Widgets\Site\SiteStatsOverview;
use App\Filament\Central\Widgets\Site\SiteTopPagesTable;
use App\Models\User;
use App\Models\WebPageview;
use App\Models\WebVisit;
use App\Support\SiteVisitMatcher;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Rastreamento do oravel.org no MESMO coletor do institucional (/api/site-track), separado pela coluna site.
 * Os paineis de campanha/leads existentes continuam olhando so' o oravel.com.br. DatabaseTransactions (ver Fase1Test).
 */
class SiteOrgTrackingTest extends TestCase
{
    use DatabaseTransactions;

    private const UA = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36';

    private function track(array $payload, string $origin = 'https://oravel.org', string $ip = '201.27.224.131')
    {
        Cache::put("ip-geo:{$ip}", ['city' => 'Campinas', 'state' => 'SP', 'hosting' => false]);

        return $this->call('POST', '/api/site-track', [], [], [], [
            'HTTP_ORIGIN' => $origin, 'HTTP_USER_AGENT' => self::UA, 'REMOTE_ADDR' => $ip, 'CONTENT_TYPE' => 'text/plain;charset=UTF-8',
        ], json_encode($payload));
    }

    private function pv(string $s = 'sess-org-0001', array $extra = []): array
    {
        return $extra + ['t' => 'pv', 'v' => 'visitor-org-01', 's' => $s, 'p' => 'page-'.$s, 'path' => '/ferramentas/preco-diaria/', 'title' => 'Calculadora', 'ref' => 'https://www.google.com/'];
    }

    private function visit(string $site, string $session, string $visitor, array $extra = []): WebVisit
    {
        return WebVisit::create($extra + [
            'site' => $site, 'visitor_token' => $visitor, 'session_token' => $session, 'ip_address' => '201.27.224.131',
            'started_at' => now(), 'last_activity_at' => now(), 'page_views' => 1, 'duration_seconds' => 30, 'landing_path' => '/',
        ]);
    }

    private function admin(): User
    {
        config(['oravel.super_admins' => ['equipe@oravel.test']]);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        return User::factory()->create(['email' => 'equipe@oravel.test']);
    }

    public function test_visita_do_oravel_org_e_gravada_com_o_site_e_os_dados_da_lista(): void
    {
        $this->track($this->pv())->assertNoContent();

        $v = WebVisit::where('session_token', 'sess-org-0001')->sole();
        $this->assertSame('oravel.org', $v->site);
        $this->assertSame('/ferramentas/preco-diaria/', $v->landing_path);
        $this->assertSame('mobile', $v->device_type);
        $this->assertSame('Campinas', $v->city);
        $this->assertSame('google.com', $v->referrer_host);
        $this->assertSame('201.27.224.131', $v->ip_address);
        $this->assertSame(1, $v->page_views);
    }

    public function test_tempo_ativo_e_a_volta_do_visitante_funcionam_no_org(): void
    {
        $this->track($this->pv('sess-org-a1'))->assertNoContent();
        $this->track(['t' => 'ping', 'v' => 'visitor-org-01', 's' => 'sess-org-a1', 'p' => 'page-sess-org-a1', 'ms' => 42000, 'sc' => 60])->assertNoContent();
        $this->track($this->pv('sess-org-b1'))->assertNoContent(); // mesma pessoa, outra visita

        $a = WebVisit::where('session_token', 'sess-org-a1')->sole();
        $this->assertSame(42, $a->duration_seconds);
        $this->assertTrue(WebVisit::where('session_token', 'sess-org-b1')->sole()->is_returning);
    }

    public function test_institucional_continua_gravando_como_oravel_com_br(): void
    {
        $this->track($this->pv('sess-com'), 'https://oravel.com.br')->assertNoContent();

        $this->assertSame('oravel.com.br', WebVisit::where('session_token', 'sess-com')->sole()->site);
    }

    public function test_host_nao_autorizado_nao_grava_nada(): void
    {
        $this->track($this->pv('sess-evil-01'), 'https://evil.example')->assertNoContent();
        $this->track($this->pv('sess-evil-02'), 'https://oravel.org.evil.example')->assertNoContent();
        $this->track($this->pv('sess-evil-03'), 'https://oravel.org')->assertNoContent(); // controle: o mesmo payload, origem valida, grava

        $this->assertSame(0, WebVisit::whereIn('session_token', ['sess-evil-01', 'sess-evil-02'])->count());
        $this->assertSame(1, WebVisit::where('session_token', 'sess-evil-03')->count());
    }

    public function test_paineis_de_campanha_ignoram_as_visitas_do_org(): void
    {
        $com = $this->visit('oravel.com.br', 's-com', 'v-com');
        $this->visit('oravel.org', 's-org1', 'v-org1');
        $this->visit('oravel.org', 's-org2', 'v-org2');
        WebPageview::create(['web_visit_id' => $com->id, 'page_token' => 'pg-com', 'path' => '/locadoras/', 'entered_at' => now()]);
        WebPageview::create(['web_visit_id' => WebVisit::where('session_token', 's-org1')->value('id'), 'page_token' => 'pg-org', 'path' => '/so-no-org/', 'entered_at' => now()]);

        $widget = new SiteStatsOverview;
        $widget->filters = ['period' => '7'];
        $stats = (fn () => $this->getStats())->call($widget);
        $this->assertSame(1, $stats[0]->getValue(), 'visitantes unicos: so o institucional');

        Livewire::actingAs($this->admin())->test(SiteTopPagesTable::class)->assertSee('/locadoras/')->assertDontSee('/so-no-org/');
        $this->assertSame(1, WebVisit::institucional()->whereIn('session_token', ['s-com', 's-org1', 's-org2'])->count());
    }

    public function test_cruzamento_lead_visita_so_considera_o_institucional(): void
    {
        $org = $this->visit('oravel.org', 's-org', 'v-org');
        WebPageview::create(['web_visit_id' => $org->id, 'page_token' => 'pg-o', 'path' => '/contato', 'entered_at' => now()]);
        $this->assertNull(SiteVisitMatcher::forMoment(now()));

        $com = $this->visit('oravel.com.br', 's-com', 'v-com');
        WebPageview::create(['web_visit_id' => $com->id, 'page_token' => 'pg-c', 'path' => '/contato', 'entered_at' => now()]);
        $this->assertSame($com->id, SiteVisitMatcher::forMoment(now())?->id);
    }

    public function test_lista_de_visitas_mostra_os_dois_sites_e_filtra_por_site(): void
    {
        $u = $this->admin();
        $com = $this->visit('oravel.com.br', 's-com', 'v-com');
        $org = $this->visit('oravel.org', 's-org', 'v-org');

        Livewire::actingAs($u)->test(ListWebVisits::class)
            ->assertCanSeeTableRecords([$com, $org])
            ->filterTable('site', 'oravel.org')->assertCanSeeTableRecords([$org])->assertCanNotSeeTableRecords([$com]);
    }
}
