<?php

namespace Tests\Feature;

use App\Filament\Central\Pages\DashboardSiteInstitucional;
use App\Filament\Central\Resources\WebVisitResource\Pages\ListWebVisits;
use App\Filament\Central\Widgets\ImplementationStats;
use App\Filament\Central\Widgets\Site\SiteClicksTable;
use App\Filament\Central\Widgets\Site\SiteSourcesTable;
use App\Filament\Central\Widgets\Site\SiteStatsOverview;
use App\Filament\Central\Widgets\Site\SiteTopPagesTable;
use App\Filament\Central\Widgets\Site\SiteVisitsChart;
use App\Models\SiteVisit;
use App\Models\User;
use App\Models\WebEvent;
use App\Models\WebPageview;
use App\Models\WebVisit;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** Painel do site institucional na Central: separado de "Acessos e Visitantes" (app). */
class SiteInstitucionalPanelTest extends TestCase
{
    use RefreshDatabase;

    private function actAsSuperAdmin(): void
    {
        $super = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br', 'password' => bcrypt('teste123'), 'tenant_id' => null]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));
    }

    private function visit(string $session, string $landing, int $seconds, array $extra = []): WebVisit
    {
        $visit = WebVisit::create(array_merge([
            'visitor_token' => 'visitor-'.$session, 'session_token' => $session, 'ip_address' => '201.27.224.130',
            'city' => 'Campinas', 'state' => 'SP', 'device_type' => 'mobile', 'browser' => 'Safari', 'os' => 'iOS',
            'landing_path' => $landing, 'page_views' => 2, 'duration_seconds' => $seconds,
            'started_at' => now(), 'last_activity_at' => now(),
        ], $extra));
        WebPageview::create(['web_visit_id' => $visit->id, 'page_token' => 'pg-'.$session.'-1', 'path' => $landing, 'active_seconds' => $seconds - 5, 'max_scroll' => 80, 'entered_at' => now()]);
        WebPageview::create(['web_visit_id' => $visit->id, 'page_token' => 'pg-'.$session.'-2', 'path' => '/contato', 'active_seconds' => 5, 'max_scroll' => 30, 'entered_at' => now()]);

        return $visit;
    }

    public function test_all_site_widgets_are_registered_in_the_central_panel_so_livewire_can_find_them(): void
    {
        // Widget fora de ->widgets() do painel = ComponentNotFoundException disfarçada de
        // "page expired"/419 (causa da janela de erro em Implantações, 2026-10-01).
        $registered = Filament::getPanel('central')->getWidgets();

        foreach ([ImplementationStats::class, SiteStatsOverview::class, SiteVisitsChart::class, SiteTopPagesTable::class, SiteSourcesTable::class, SiteClicksTable::class] as $widget) {
            $this->assertContains($widget, $registered, "{$widget} precisa estar registrado em CentralPanelProvider::widgets()");
        }
    }

    public function test_dashboard_and_widgets_render_with_real_numbers(): void
    {
        $this->actAsSuperAdmin();
        $a = $this->visit('sessao-a', '/locadoras/', 65, ['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'locadoras']);
        $this->visit('sessao-b', '/', 20, ['referrer_host' => 'instagram.com']);
        WebEvent::create(['web_visit_id' => $a->id, 'type' => 'click', 'label' => 'WhatsApp', 'path' => '/locadoras/', 'occurred_at' => now()]);

        Livewire::test(DashboardSiteInstitucional::class)->assertSuccessful()->assertSee('Site Institucional');
        Livewire::test(SiteStatsOverview::class)->assertSee('Visitantes únicos')->assertSee('Cliques em CTAs');
        Livewire::test(SiteTopPagesTable::class)->assertSee('/locadoras/')->assertSee('/contato');
        Livewire::test(SiteSourcesTable::class)->assertSee('google')->assertSee('instagram.com');
        Livewire::test(SiteClicksTable::class)->assertSee('WhatsApp');
        Livewire::test(SiteVisitsChart::class)->assertSuccessful();
    }

    public function test_visits_list_shows_journey_and_stays_separate_from_app_visits(): void
    {
        $this->actAsSuperAdmin();
        $visit = $this->visit('sessao-c', '/locadoras/', 90);
        $app = SiteVisit::create([
            'id' => (string) Str::uuid(), 'visitor_token' => 'app-v', 'session_token' => 'app-s', 'landing_path' => '/admin/login',
            'started_at' => now(), 'last_activity_at' => now(), 'page_views' => 1, 'duration_seconds' => 0,
        ]);

        Livewire::test(ListWebVisits::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$visit])
            ->assertCountTableRecords(1)
            ->mountTableAction('view', $visit)
            ->assertSee('/contato')
            ->assertSee('Jornada');

        $this->assertSame(1, WebVisit::count());
        $this->assertSame(1, SiteVisit::withoutGlobalScopes()->count());
        $this->assertNotNull($app->id);
    }
}
