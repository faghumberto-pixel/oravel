<?php

namespace Tests\Feature;

use App\Models\SiteVisit;
use App\Services\IpGeolocationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regressao (log de PROD 2026-10-01): visitas de fora do Brasil traziam o
 * codigo de regiao com 3+ letras (ex.: "ENG") e estouravam site_visits.state
 * (varchar(2)) -- a visita nao era gravada.
 */
class SiteVisitForeignRegionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_visit_with_a_three_letter_region_code_is_saved(): void
    {
        Cache::flush();
        Http::fake(['ip-api.com/*' => Http::response(['status' => 'success', 'city' => 'London', 'region' => 'ENG'], 200)]);

        $geo = app(IpGeolocationService::class)->locate('8.8.4.4');
        $this->assertSame('ENG', $geo['state']);

        $visit = new SiteVisit;
        $visit->id = (string) Str::uuid();
        $visit->visitor_token = (string) Str::uuid();
        $visit->session_token = (string) Str::uuid();
        $visit->ip_address = '8.8.4.4';
        $visit->city = $geo['city'];
        $visit->state = $geo['state'];
        $visit->landing_path = '/';
        $visit->page_views = 1;
        $visit->started_at = now();
        $visit->last_activity_at = now();
        $visit->duration_seconds = 0;
        $visit->save();

        $this->assertSame('ENG', SiteVisit::where('id', $visit->id)->value('state'));
    }

    public function test_region_longer_than_the_column_is_truncated_not_fatal(): void
    {
        Cache::flush();
        Http::fake(['ip-api.com/*' => Http::response(['status' => 'success', 'city' => 'X', 'region' => 'REGIAO-MUITO-LONGA-DEMAIS'], 200)]);

        $geo = app(IpGeolocationService::class)->locate('1.1.1.1');

        $this->assertSame(10, mb_strlen($geo['state']));
    }
}
