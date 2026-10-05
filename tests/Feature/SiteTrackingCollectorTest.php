<?php

namespace Tests\Feature;

use App\Models\SiteVisit;
use App\Models\WebEvent;
use App\Models\WebPageview;
use App\Models\WebVisit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Coletor do analytics do site institucional: separado do tracking do app (site_visits). */
class SiteTrackingCollectorTest extends TestCase
{
    use DatabaseTransactions;

    private const UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6.1 Mobile/15E148 Safari/604.1';

    private function track(array $payload, string $origin = 'https://oravel.com.br', string $ua = self::UA, string $ip = '201.27.224.130', array $place = ['city' => 'Campinas', 'state' => 'SP', 'hosting' => false])
    {
        Cache::put("ip-geo:{$ip}", $place);

        return $this->call(
            'POST', '/api/site-track', [], [], [],
            ['HTTP_ORIGIN' => $origin, 'HTTP_USER_AGENT' => $ua, 'REMOTE_ADDR' => $ip, 'CONTENT_TYPE' => 'text/plain;charset=UTF-8'],
            json_encode($payload)
        );
    }

    private function pv(array $extra = []): array
    {
        return array_merge(['t' => 'pv', 'v' => 'visitor-0001', 's' => 'session-0001', 'p' => 'page-0001', 'path' => '/locadoras/', 'title' => 'Locadoras'], $extra);
    }

    public function test_pageview_creates_visit_and_pageview_without_touching_app_visits(): void
    {
        $appVisitsBefore = SiteVisit::withoutGlobalScopes()->count();

        $this->track($this->pv(['ref' => 'https://www.google.com/search?q=oravel', 'utm_source' => 'google', 'utm_medium' => 'cpc']))->assertNoContent();

        $visit = WebVisit::where('session_token', 'session-0001')->sole();
        $this->assertSame('/locadoras/', $visit->landing_path);
        $this->assertSame('mobile', $visit->device_type);
        $this->assertSame('Safari', $visit->browser);
        $this->assertSame('iOS', $visit->os);
        $this->assertSame('Campinas', $visit->city);
        $this->assertSame('google.com', $visit->referrer_host);
        $this->assertSame('google / cpc', $visit->sourceLabel());
        $this->assertSame(1, $visit->page_views);
        $this->assertSame(1, WebPageview::count());
        // Separado do app: nenhuma linha em site_visits.
        $this->assertSame($appVisitsBefore, SiteVisit::withoutGlobalScopes()->count());
    }

    public function test_active_time_and_scroll_only_grow_and_sum_into_the_visit(): void
    {
        $this->track($this->pv());
        $this->track(['t' => 'ping', 'v' => 'visitor-0001', 's' => 'session-0001', 'p' => 'page-0001', 'ms' => 15000, 'sc' => 40]);
        $this->track(['t' => 'ping', 'v' => 'visitor-0001', 's' => 'session-0001', 'p' => 'page-0001', 'ms' => 5000, 'sc' => 10]); // fora de ordem
        $this->track(['t' => 'leave', 'v' => 'visitor-0001', 's' => 'session-0001', 'p' => 'page-0001', 'ms' => 42000, 'sc' => 85]);

        $page = WebPageview::sole();
        $this->assertSame(42, $page->active_seconds);
        $this->assertSame(85, $page->max_scroll);

        // Segunda página da mesma sessão: o tempo da visita é a soma.
        $this->track($this->pv(['p' => 'page-0002', 'path' => '/contato']));
        $this->track(['t' => 'leave', 'v' => 'visitor-0001', 's' => 'session-0001', 'p' => 'page-0002', 'ms' => 8000, 'sc' => 100]);

        $visit = WebVisit::sole();
        $this->assertSame(2, $visit->page_views);
        $this->assertSame(50, $visit->duration_seconds);
    }

    public function test_pageview_resend_does_not_duplicate_and_click_is_recorded(): void
    {
        $this->track($this->pv());
        $this->track($this->pv());
        $this->assertSame(1, WebPageview::count());

        $this->track(['t' => 'click', 'v' => 'visitor-0001', 's' => 'session-0001', 'p' => 'page-0001', 'label' => 'WhatsApp', 'path' => '/locadoras/']);
        $event = WebEvent::sole();
        $this->assertSame('WhatsApp', $event->label);
        $this->assertSame('click', $event->type);
    }

    public function test_returning_visitor_is_flagged(): void
    {
        $this->track($this->pv());
        $this->track($this->pv(['s' => 'session-0002', 'p' => 'page-0009']));

        $this->assertFalse(WebVisit::where('session_token', 'session-0001')->value('is_returning'));
        $this->assertTrue((bool) WebVisit::where('session_token', 'session-0002')->value('is_returning'));
    }

    public function test_foreign_origin_bots_and_garbage_are_ignored_silently(): void
    {
        $this->track($this->pv(), 'https://site-malicioso.com')->assertNoContent();
        $this->track($this->pv(), 'https://oravel.com.br', 'Googlebot/2.1 (+http://www.google.com/bot.html)')->assertNoContent();
        $this->track($this->pv(), 'https://oravel.com.br', 'curl/8.5.0')->assertNoContent();
        $this->call('POST', '/api/site-track', [], [], [], ['HTTP_ORIGIN' => 'https://oravel.com.br', 'HTTP_USER_AGENT' => self::UA], 'isso nao e json')->assertNoContent();
        $this->track(['t' => 'pv', 'v' => 'x', 's' => 'y', 'p' => 'z'])->assertNoContent(); // tokens curtos demais

        $this->assertSame(0, WebVisit::count());
        $this->assertSame(0, WebPageview::count());
    }

    public function test_prune_removes_only_visits_older_than_thirteen_months(): void
    {
        $old = WebVisit::create(['visitor_token' => 'v-old', 'session_token' => 's-old', 'started_at' => now()->subMonths(14), 'page_views' => 1]);
        WebPageview::create(['web_visit_id' => $old->id, 'page_token' => 'p-old', 'path' => '/', 'entered_at' => now()->subMonths(14)]);
        $recent = WebVisit::create(['visitor_token' => 'v-new', 'session_token' => 's-new', 'started_at' => now()->subMonths(2), 'page_views' => 1]);

        $this->artisan('web-analytics:prune')->assertSuccessful();

        $this->assertNull(WebVisit::find($old->id));
        $this->assertSame(0, WebPageview::where('page_token', 'p-old')->count());
        $this->assertNotNull(WebVisit::find($recent->id));
    }

    public function test_datacenter_ip_is_ignored_and_its_followup_events_too(): void
    {
        $ip = '34.128.4.35';
        $dallas = ['city' => 'Dallas', 'state' => 'TX', 'hosting' => true];

        $this->track($this->pv(['s' => 'session-bot', 'p' => 'page-bot']), ip: $ip, place: $dallas)->assertNoContent();
        $this->track(['t' => 'click', 'v' => 'visitor-0001', 's' => 'session-bot', 'label' => 'WhatsApp'], ip: $ip, place: $dallas)->assertNoContent();

        $this->assertSame(0, WebVisit::where('session_token', 'session-bot')->count());
        $this->assertSame(0, WebEvent::count());
    }

    public function test_old_cache_entry_without_hosting_flag_is_looked_up_again(): void
    {
        $ip = '104.197.69.115';
        Cache::put("ip-geo:{$ip}", ['city' => 'Council Bluffs', 'state' => 'IA']); // formato antigo
        Http::fake(['ip-api.com/*' => Http::response(['status' => 'success', 'city' => 'Council Bluffs', 'region' => 'IA', 'hosting' => true])]);

        $this->call(
            'POST', '/api/site-track', [], [], [],
            ['HTTP_ORIGIN' => 'https://oravel.com.br', 'HTTP_USER_AGENT' => self::UA, 'REMOTE_ADDR' => $ip, 'CONTENT_TYPE' => 'text/plain;charset=UTF-8'],
            json_encode($this->pv(['s' => 'session-old', 'p' => 'page-old']))
        )->assertNoContent();

        $this->assertSame(0, WebVisit::where('session_token', 'session-old')->count());
    }

    public function test_purge_bots_lists_by_default_and_deletes_only_datacenter_visits_with_apply(): void
    {
        Cache::put('ip-geo:34.128.4.35', ['city' => 'Dallas', 'state' => 'TX', 'hosting' => true]);
        Cache::put('ip-geo:201.27.224.130', ['city' => 'Campinas', 'state' => 'SP', 'hosting' => false]);
        $bot = WebVisit::create(['visitor_token' => 'v-bot', 'session_token' => 's-bot', 'ip_address' => '34.128.4.35', 'started_at' => now(), 'page_views' => 1]);
        $human = WebVisit::create(['visitor_token' => 'v-hum', 'session_token' => 's-hum', 'ip_address' => '201.27.224.130', 'started_at' => now(), 'page_views' => 1]);
        WebPageview::create(['web_visit_id' => $bot->id, 'page_token' => 'p-bot', 'path' => '/', 'entered_at' => now()]);

        $this->artisan('web-analytics:purge-bots')->assertSuccessful();
        $this->assertNotNull(WebVisit::find($bot->id)); // dry-run não apaga

        $this->artisan('web-analytics:purge-bots', ['--apply' => true])->assertSuccessful();
        $this->assertNull(WebVisit::find($bot->id));
        $this->assertSame(0, WebPageview::where('page_token', 'p-bot')->count());
        $this->assertNotNull(WebVisit::find($human->id));
    }
}
