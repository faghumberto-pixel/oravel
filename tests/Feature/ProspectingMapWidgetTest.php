<?php

namespace Tests\Feature;

use App\Filament\Central\Widgets\ProspectingMapWidget;
use App\Models\SalesLead;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProspectingMapWidgetTest extends TestCase
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

    public function test_only_leads_currently_in_prospeccao_appear_on_the_map(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('central'));

        SalesLead::create([
            'company_name' => 'Prospecção com endereço',
            'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
            'latitude' => -23.55, 'longitude' => -46.63,
        ]);
        SalesLead::create([
            'company_name' => 'Já qualificado (não deve aparecer)',
            'pipeline_stage' => SalesLead::STAGE_CONTATO_QUALIFICADO,
            'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
            'latitude' => -22.9, 'longitude' => -43.2,
        ]);
        SalesLead::create([
            'company_name' => 'Prospecção sem endereço (não deve aparecer)',
            'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
        ]);

        $widget = new ProspectingMapWidget;
        $leads = $widget->getLeads();

        $this->assertCount(1, $leads);
        $this->assertSame('Prospecção com endereço', $leads[0]['company_name']);
    }

    public function test_dashboard_crm_loads_with_both_maps_side_by_side(): void
    {
        $this->actingAs($this->superAdmin());

        $response = $this->get('/central/dashboard-crm');
        $response->assertOk();
    }

    public function test_prospecting_map_widget_survives_a_real_livewire_update_post(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);

        $response = $this->get('/central/dashboard-crm');
        $response->assertOk();
        $html = $response->getContent();

        preg_match_all('/wire:snapshot="([^"]*)"/', $html, $snapshots);
        $csrfMatch = [];
        preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $csrfMatch);
        $csrfToken = $csrfMatch[1];

        $found = false;
        foreach ($snapshots[1] as $encoded) {
            $json = html_entity_decode($encoded);
            $data = json_decode($json, true);
            if (($data['memo']['name'] ?? '') !== 'app.filament.central.widgets.prospecting-map-widget') {
                continue;
            }
            $found = true;

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

        $this->assertTrue($found, 'ProspectingMapWidget não encontrado na página.');
    }
}
