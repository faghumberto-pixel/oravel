<?php

namespace Tests\Feature;

use App\Filament\Central\Pages\Kanban;
use App\Models\SalesLead;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KanbanFreeStageMoveTest extends TestCase
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

    public function test_can_move_lead_freely_between_open_stages(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $lead = SalesLead::create([
            'company_name' => 'Empresa Teste Kanban',
            'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'segment' => 'industrial_hospitalar',
            'source' => SalesLead::SOURCE_SITE,
        ]);

        Livewire::test(Kanban::class)
            ->call('moveToStage', $lead->id, SalesLead::STAGE_PROPOSTA_ENVIADA)
            ->assertHasNoErrors();

        $this->assertSame(SalesLead::STAGE_PROPOSTA_ENVIADA, $lead->fresh()->pipeline_stage);

        // volta pra tras tambem tem que funcionar (movimentacao livre, nao so' avancar)
        Livewire::test(Kanban::class)
            ->call('moveToStage', $lead->id, SalesLead::STAGE_CONTATO_QUALIFICADO)
            ->assertHasNoErrors();

        $this->assertSame(SalesLead::STAGE_CONTATO_QUALIFICADO, $lead->fresh()->pipeline_stage);
    }

    public function test_cannot_move_to_ganho_or_perdido_via_free_move(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $lead = SalesLead::create([
            'company_name' => 'Empresa Teste Kanban 2',
            'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'segment' => 'industrial_hospitalar',
            'source' => SalesLead::SOURCE_SITE,
        ]);

        Livewire::test(Kanban::class)
            ->call('moveToStage', $lead->id, SalesLead::STAGE_GANHO);

        $this->assertSame(SalesLead::STAGE_PROSPECCAO, $lead->fresh()->pipeline_stage);
    }

    public function test_kanban_board_reflects_the_moved_stage_on_a_real_livewire_update_post(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);

        $lead = SalesLead::create([
            'company_name' => 'Empresa Teste Kanban Real Post',
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
        $this->assertNotNull($kanbanSnapshotRaw, 'Kanban page snapshot not found.');

        preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $m);
        $csrfToken = $m[1];

        $payload = [
            'components' => [
                [
                    'snapshot' => $kanbanSnapshotRaw,
                    'updates' => [],
                    'calls' => [
                        ['path' => '', 'method' => 'moveToStage', 'params' => [$lead->id, SalesLead::STAGE_DEMONSTRACAO_REALIZADA]],
                    ],
                ],
            ],
        ];

        $updateResponse = $this->postJson('/livewire/update', $payload, [
            'X-CSRF-TOKEN' => $csrfToken,
            'X-Livewire' => 'true',
        ]);

        $updateResponse->assertOk();
        $this->assertSame(SalesLead::STAGE_DEMONSTRACAO_REALIZADA, $lead->fresh()->pipeline_stage);
    }
}
