<?php

namespace Tests\Feature;

use App\Filament\Central\Widgets\LeadsBySegmentChart;
use App\Filament\Central\Widgets\LeadsBySourceChart;
use App\Filament\Central\Widgets\LeadsCreatedTrendChart;
use App\Filament\Central\Widgets\WonLostTrendChart;
use App\Models\SalesLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCrmNewWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();

        return $super;
    }

    public function test_dashboard_crm_loads_and_new_charts_return_real_data(): void
    {
        $this->actingAs($this->superAdmin());

        SalesLead::create([
            'company_name' => 'Empresa Chart 1', 'pipeline_stage' => SalesLead::STAGE_GANHO,
            'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
        ]);
        SalesLead::create([
            'company_name' => 'Empresa Chart 2', 'pipeline_stage' => SalesLead::STAGE_PERDIDO,
            'segment' => 'construcao_civil', 'source' => SalesLead::SOURCE_INDICACAO,
        ]);

        $response = $this->get('/central/dashboard-crm');
        $response->assertOk();

        // Widgets de dashboard sao lazy-load por padrao no Filament (esqueleto
        // no HTML inicial, conteudo real so' depois de um segundo request via
        // JS) -- assertSee() no texto do heading nao prova nada aqui. Chama
        // getData() (protected) via reflection pra confirmar que a query
        // real bate.
        $segmentData = $this->callGetData(LeadsBySegmentChart::class);
        $this->assertContains(1, $segmentData['datasets'][0]['data']);

        $sourceData = $this->callGetData(LeadsBySourceChart::class);
        $this->assertContains(1, $sourceData['datasets'][0]['data']);

        $trendData = $this->callGetData(LeadsCreatedTrendChart::class);
        $this->assertSame(2, array_sum($trendData['datasets'][0]['data']));
        $this->assertCount(6, $trendData['labels']);

        $wonLostData = $this->callGetData(WonLostTrendChart::class);
        $this->assertSame(1, array_sum($wonLostData['datasets'][0]['data']));
        $this->assertSame(1, array_sum($wonLostData['datasets'][1]['data']));
    }

    private function callGetData(string $widgetClass): array
    {
        $widget = new $widgetClass;
        $method = new \ReflectionMethod($widget, 'getData');
        $method->setAccessible(true);

        return $method->invoke($widget);
    }

    public function test_new_chart_widgets_survive_a_real_livewire_update_post(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);

        SalesLead::create([
            'company_name' => 'Empresa Chart Real Post', 'pipeline_stage' => SalesLead::STAGE_GANHO,
            'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
        ]);

        $response = $this->get('/central/dashboard-crm');
        $response->assertOk();
        $html = $response->getContent();

        preg_match_all('/wire:snapshot="([^"]*)"/', $html, $snapshots);
        $csrfMatch = [];
        preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $csrfMatch);
        $csrfToken = $csrfMatch[1];

        $chartComponentNames = [
            'app.filament.central.widgets.leads-by-segment-chart',
            'app.filament.central.widgets.leads-by-source-chart',
            'app.filament.central.widgets.leads-created-trend-chart',
            'app.filament.central.widgets.won-lost-trend-chart',
        ];

        $found = [];
        foreach ($snapshots[1] as $encoded) {
            $json = html_entity_decode($encoded);
            $data = json_decode($json, true);
            $name = $data['memo']['name'] ?? '';

            if (! in_array($name, $chartComponentNames, true)) {
                continue;
            }

            $found[] = $name;

            $payload = [
                'components' => [
                    ['snapshot' => $json, 'updates' => [], 'calls' => []],
                ],
            ];

            $updateResponse = $this->postJson('/livewire/update', $payload, [
                'X-CSRF-TOKEN' => $csrfToken,
                'X-Livewire' => 'true',
            ]);

            $updateResponse->assertOk();
        }

        $this->assertCount(4, $found, 'Nem todos os 4 gráficos novos foram encontrados na página: '.implode(', ', $found));
    }
}
