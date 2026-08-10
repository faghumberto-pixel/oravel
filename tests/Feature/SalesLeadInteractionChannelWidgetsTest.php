<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\SalesLeadResource\Pages\ListSalesLeads;
use App\Filament\Central\Resources\SalesLeadResource\Widgets\InteractionChannelChart;
use App\Filament\Central\Resources\SalesLeadResource\Widgets\InteractionChannelStats;
use App\Models\SalesLead;
use App\Models\SalesLeadInteraction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesLeadInteractionChannelWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);

        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        return $super;
    }

    public function test_channel_stats_count_distinct_leads_per_channel(): void
    {
        $super = $this->actingAsSuperAdmin();

        $leadA = SalesLead::create(['company_name' => 'Lead A']);
        $leadB = SalesLead::create(['company_name' => 'Lead B']);
        $leadC = SalesLead::create(['company_name' => 'Lead C']);

        // Lead A: email + telefone (conta em ambos os canais).
        $leadA->interactions()->create(['contact_date' => now(), 'channel' => SalesLeadInteraction::CHANNEL_EMAIL, 'summary' => 'x', 'user_id' => $super->id, 'stage_at_time' => SalesLead::STAGE_PROSPECCAO]);
        $leadA->interactions()->create(['contact_date' => now(), 'channel' => SalesLeadInteraction::CHANNEL_TELEFONE, 'summary' => 'x', 'user_id' => $super->id, 'stage_at_time' => SalesLead::STAGE_PROSPECCAO]);

        // Lead B: 2 emails (mesmo canal duas vezes -- deve contar 1 lead, nao 2).
        $leadB->interactions()->create(['contact_date' => now(), 'channel' => SalesLeadInteraction::CHANNEL_EMAIL, 'summary' => 'x', 'user_id' => $super->id, 'stage_at_time' => SalesLead::STAGE_PROSPECCAO]);
        $leadB->interactions()->create(['contact_date' => now(), 'channel' => SalesLeadInteraction::CHANNEL_EMAIL, 'summary' => 'x', 'user_id' => $super->id, 'stage_at_time' => SalesLead::STAGE_PROSPECCAO]);

        // Lead C: visita.
        $leadC->interactions()->create(['contact_date' => now(), 'channel' => SalesLeadInteraction::CHANNEL_VISITA, 'summary' => 'x', 'user_id' => $super->id, 'stage_at_time' => SalesLead::STAGE_PROSPECCAO]);

        Livewire::test(InteractionChannelStats::class)->assertSuccessful();

        $this->assertSame(2, SalesLead::whereHas('interactions', fn ($q) => $q->where('channel', SalesLeadInteraction::CHANNEL_EMAIL))->count());
        $this->assertSame(1, SalesLead::whereHas('interactions', fn ($q) => $q->where('channel', SalesLeadInteraction::CHANNEL_TELEFONE))->count());
        $this->assertSame(1, SalesLead::whereHas('interactions', fn ($q) => $q->where('channel', SalesLeadInteraction::CHANNEL_VISITA))->count());
        $this->assertSame(0, SalesLead::whereHas('interactions', fn ($q) => $q->where('channel', SalesLeadInteraction::CHANNEL_WHATSAPP))->count());
    }

    public function test_interaction_channel_filter_matches_the_stat_card_link(): void
    {
        $super = $this->actingAsSuperAdmin();

        $comEmail = SalesLead::create(['company_name' => 'Contatado por Email']);
        $comEmail->interactions()->create(['contact_date' => now(), 'channel' => SalesLeadInteraction::CHANNEL_EMAIL, 'summary' => 'x', 'user_id' => $super->id, 'stage_at_time' => SalesLead::STAGE_PROSPECCAO]);

        $comWhatsapp = SalesLead::create(['company_name' => 'Contatado por WhatsApp']);
        $comWhatsapp->interactions()->create(['contact_date' => now(), 'channel' => SalesLeadInteraction::CHANNEL_WHATSAPP, 'summary' => 'x', 'user_id' => $super->id, 'stage_at_time' => SalesLead::STAGE_PROSPECCAO]);

        $semContato = SalesLead::create(['company_name' => 'Nunca Contatado']);

        Livewire::test(ListSalesLeads::class)
            ->filterTable('interaction_channel', SalesLeadInteraction::CHANNEL_EMAIL)
            ->assertCanSeeTableRecords([$comEmail])
            ->assertCanNotSeeTableRecords([$comWhatsapp, $semContato]);
    }

    public function test_channel_chart_widget_renders_without_error(): void
    {
        $super = $this->actingAsSuperAdmin();

        $lead = SalesLead::create(['company_name' => 'Lead A']);
        $lead->interactions()->create(['contact_date' => now(), 'channel' => SalesLeadInteraction::CHANNEL_REUNIAO_ONLINE, 'summary' => 'x', 'user_id' => $super->id, 'stage_at_time' => SalesLead::STAGE_PROSPECCAO]);

        Livewire::test(InteractionChannelChart::class)->assertSuccessful();
    }

    public function test_channel_labels_include_the_new_meeting_and_visit_channels(): void
    {
        $labels = SalesLeadInteraction::channelLabels();

        $this->assertArrayHasKey(SalesLeadInteraction::CHANNEL_REUNIAO_PRESENCIAL, $labels);
        $this->assertArrayHasKey(SalesLeadInteraction::CHANNEL_REUNIAO_ONLINE, $labels);
        $this->assertArrayHasKey(SalesLeadInteraction::CHANNEL_VISITA, $labels);
        $this->assertArrayNotHasKey('presencial', $labels);
    }
}
