<?php

namespace Tests\Feature;

use App\Filament\Central\Pages\Kanban;
use App\Models\SalesLead;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KanbanSegmentSourceEditTest extends TestCase
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

    public function test_can_update_segment_and_source_from_the_kanban_card(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $lead = SalesLead::create([
            'company_name' => 'Empresa Teste Segmento Kanban',
            'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'segment' => 'industrial_hospitalar',
            'source' => SalesLead::SOURCE_SITE,
        ]);

        Livewire::test(Kanban::class)
            ->call('updateSegment', $lead->id, 'construcao_civil')
            ->call('updateSource', $lead->id, SalesLead::SOURCE_INDICACAO)
            ->assertHasNoErrors();

        $lead->refresh();
        $this->assertSame('construcao_civil', $lead->segment);
        $this->assertSame(SalesLead::SOURCE_INDICACAO, $lead->source);
    }

    public function test_kanban_reflects_segment_source_update_on_a_real_livewire_update_post(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);

        $lead = SalesLead::create([
            'company_name' => 'Empresa Real Post Segmento',
            'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'segment' => 'industrial_hospitalar',
            'source' => SalesLead::SOURCE_SITE,
        ]);

        $response = $this->get('/central/kanban');
        $response->assertOk();
        $html = $response->getContent();

        preg_match_all('/wire:snapshot="([^"]*)"/', $html, $snapshots);
        $kanbanSnapshotRaw = null;
        foreach ($snapshots[1] as $encoded) {
            $json = html_entity_decode($encoded);
            $data = json_decode($json, true);
            if (($data['memo']['name'] ?? '') === 'app.filament.central.pages.kanban') {
                $kanbanSnapshotRaw = $json;
            }
        }
        $this->assertNotNull($kanbanSnapshotRaw);

        preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $m);
        $csrfToken = $m[1];

        $payload = [
            'components' => [
                [
                    'snapshot' => $kanbanSnapshotRaw,
                    'updates' => [],
                    'calls' => [
                        ['path' => '', 'method' => 'updateSegment', 'params' => [$lead->id, 'construcao_civil']],
                    ],
                ],
            ],
        ];

        $updateResponse = $this->postJson('/livewire/update', $payload, [
            'X-CSRF-TOKEN' => $csrfToken,
            'X-Livewire' => 'true',
        ]);

        $updateResponse->assertOk();
        $this->assertSame('construcao_civil', $lead->fresh()->segment);
    }
}
