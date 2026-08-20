<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\SalesLeadResource\Pages\EditSalesLead;
use App\Filament\Central\Resources\SalesLeadResource\RelationManagers\TimelineRelationManager;
use App\Models\SalesLead;
use App\Models\SalesLeadAppointment;
use App\Models\SalesLeadInteraction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesLeadTimelineTest extends TestCase
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

    public function test_timeline_merges_interactions_and_appointments_most_recent_first(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $lead = SalesLead::create([
            'company_name' => 'Empresa Timeline', 'pipeline_stage' => SalesLead::STAGE_CONTATO_QUALIFICADO,
            'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
        ]);

        $lead->interactions()->create([
            'contact_date' => now()->subDays(5), 'channel' => SalesLeadInteraction::CHANNEL_TELEFONE,
            'summary' => 'Primeiro contato por telefone.', 'user_id' => $super->id, 'stage_at_time' => $lead->pipeline_stage,
        ]);
        $lead->appointments()->create([
            'title' => 'Demo agendada', 'type' => SalesLeadAppointment::TYPE_DEMONSTRACAO,
            'scheduled_at' => now()->subDays(2), 'status' => SalesLeadAppointment::STATUS_CONCLUIDO,
        ]);
        $lead->interactions()->create([
            'contact_date' => now(), 'channel' => SalesLeadInteraction::CHANNEL_WHATSAPP,
            'summary' => 'Follow up mais recente.', 'user_id' => $super->id, 'stage_at_time' => $lead->pipeline_stage,
        ]);

        $component = Livewire::test(TimelineRelationManager::class, ['ownerRecord' => $lead, 'pageClass' => EditSalesLead::class]);

        $items = $component->instance()->getTimelineItems();

        $this->assertCount(3, $items);
        $this->assertSame('Follow up mais recente.', $items[0]['body']);
        $this->assertSame('interaction', $items[0]['kind']);
        $this->assertSame('appointment', $items[1]['kind']);
        $this->assertSame('Primeiro contato por telefone.', $items[2]['body']);

        $component->assertOk();
    }

    public function test_timeline_renders_without_error_when_lead_has_no_history(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $lead = SalesLead::create([
            'company_name' => 'Empresa Sem Histórico', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
        ]);

        Livewire::test(TimelineRelationManager::class, ['ownerRecord' => $lead, 'pageClass' => EditSalesLead::class])
            ->assertOk();
    }
}
