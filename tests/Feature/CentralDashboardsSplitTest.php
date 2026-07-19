<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralDashboardsSplitTest extends TestCase
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

    public function test_dashboard_saas_loads_with_saas_widgets_only(): void
    {
        $this->actingAs($this->superAdmin());

        $response = $this->get('/central');
        $response->assertOk();
        $response->assertSee('Dashboard SaaS');
        $response->assertDontSee('Mapa Comercial', false);
    }

    public function test_dashboard_crm_loads_with_crm_widgets_only(): void
    {
        $this->actingAs($this->superAdmin());

        $response = $this->get('/central/dashboard-crm');
        $response->assertOk();
        $response->assertSee('Dashboard CRM');
    }

    public function test_extra_saas_widgets_survive_a_real_livewire_update_post(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);

        $response = $this->get('/central');
        $response->assertOk();
        $html = $response->getContent();

        preg_match_all('/wire:snapshot="([^"]*)"/', $html, $snapshots);

        $csrfMatch = [];
        preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $csrfMatch);
        $csrfToken = $csrfMatch[1];

        $failures = [];
        foreach ($snapshots[1] as $encoded) {
            $json = html_entity_decode($encoded);
            $data = json_decode($json, true);
            $name = $data['memo']['name'] ?? '';
            if (! str_contains((string) $name, 'chart') && ! str_contains((string) $name, 'stats-overview')) {
                continue;
            }

            $payload = [
                'components' => [
                    ['snapshot' => $json, 'updates' => [], 'calls' => []],
                ],
            ];

            $updateResponse = $this->postJson('/livewire/update', $payload, [
                'X-CSRF-TOKEN' => $csrfToken,
                'X-Livewire' => 'true',
            ]);

            if ($updateResponse->getStatusCode() !== 200) {
                $failures[] = $name.' => '.$updateResponse->getStatusCode();
            }
        }

        $this->assertEmpty($failures, 'Widgets failing livewire update: '.implode(', ', $failures));
    }
}
