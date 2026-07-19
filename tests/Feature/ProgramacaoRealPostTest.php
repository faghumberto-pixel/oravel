<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramacaoRealPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_widget_survives_a_real_livewire_update_post(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();

        $this->actingAs($super);

        $response = $this->get('/central/programacao');
        if ($response->isRedirect()) {
            $response = $this->get($response->headers->get('Location'));
        }
        $response->assertOk();
        $html = $response->getContent();

        preg_match_all('/wire:snapshot="([^"]*)"/', $html, $snapshots);

        $calendarSnapshotRaw = null;
        foreach ($snapshots[1] as $encoded) {
            $json = html_entity_decode($encoded);
            $data = json_decode($json, true);
            if (($data['data']['model'] ?? null) === 'App\\Models\\SalesLeadAppointment') {
                $calendarSnapshotRaw = $json;
            }
        }

        $this->assertNotNull($calendarSnapshotRaw, 'SalesAgendaWidget snapshot not found on the Programacao page.');

        preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $m);
        $csrfToken = $m[1];

        $payload = [
            'components' => [
                [
                    'snapshot' => $calendarSnapshotRaw,
                    'updates' => [],
                    'calls' => [
                        ['path' => '', 'method' => 'fetchEvents', 'params' => [['start' => now()->startOfMonth()->toIso8601String(), 'end' => now()->endOfMonth()->toIso8601String(), 'timeZone' => 'UTC']]],
                    ],
                ],
            ],
        ];

        $updateResponse = $this->postJson('/livewire/update', $payload, [
            'X-CSRF-TOKEN' => $csrfToken,
            'X-Livewire' => 'true',
        ]);

        $updateResponse->assertOk();
    }
}
