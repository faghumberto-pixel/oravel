<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\SalesLeadResource\Pages\EditSalesLead;
use App\Filament\Central\Resources\SalesLeadResource\RelationManagers\InteractionsRelationManager;
use App\Models\SalesLead;
use App\Models\SalesLeadInteraction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesLeadFollowUpTest extends TestCase
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

    public function test_quick_note_creates_a_timestamped_interaction(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $lead = SalesLead::create([
            'company_name' => 'Empresa Follow Up', 'pipeline_stage' => SalesLead::STAGE_CONTATO_QUALIFICADO,
            'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
        ]);

        Livewire::test(InteractionsRelationManager::class, ['ownerRecord' => $lead, 'pageClass' => EditSalesLead::class])
            ->set('quickNote', 'Cliente pediu pra ligar semana que vem.')
            ->call('addQuickNote')
            ->assertSet('quickNote', '');

        $interaction = $lead->interactions()->first();
        $this->assertNotNull($interaction);
        $this->assertSame('Cliente pediu pra ligar semana que vem.', $interaction->summary);
        $this->assertSame(SalesLeadInteraction::CHANNEL_OUTRO, $interaction->channel);
        $this->assertSame($super->id, $interaction->user_id);
        $this->assertSame(SalesLead::STAGE_CONTATO_QUALIFICADO, $interaction->stage_at_time);
        // diffInSeconds (nao greaterThanOrEqualTo) de proposito -- a coluna
        // contact_date e' timestamp sem microssegundos, truncar pra baixo
        // pode deixar o valor salvo alguns ms "antes" de um now() capturado
        // com microssegundos no teste, mesmo sendo praticamente o mesmo
        // instante.
        $this->assertLessThanOrEqual(2, $interaction->contact_date->diffInSeconds(now()));
    }

    public function test_blank_quick_note_does_not_create_an_interaction(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $lead = SalesLead::create([
            'company_name' => 'Empresa Follow Up Vazio', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
        ]);

        Livewire::test(InteractionsRelationManager::class, ['ownerRecord' => $lead, 'pageClass' => EditSalesLead::class])
            ->set('quickNote', '   ')
            ->call('addQuickNote');

        $this->assertSame(0, $lead->interactions()->count());
    }

    public function test_date_filter_only_returns_interactions_in_range(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $lead = SalesLead::create([
            'company_name' => 'Empresa Filtro Data', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'segment' => 'industrial_hospitalar', 'source' => SalesLead::SOURCE_SITE,
        ]);

        $lead->interactions()->create([
            'contact_date' => now()->subDays(10), 'channel' => SalesLeadInteraction::CHANNEL_OUTRO,
            'summary' => 'Contato antigo', 'user_id' => auth()->id(), 'stage_at_time' => $lead->pipeline_stage,
        ]);
        $lead->interactions()->create([
            'contact_date' => now(), 'channel' => SalesLeadInteraction::CHANNEL_OUTRO,
            'summary' => 'Contato recente', 'user_id' => auth()->id(), 'stage_at_time' => $lead->pipeline_stage,
        ]);

        Livewire::test(InteractionsRelationManager::class, ['ownerRecord' => $lead, 'pageClass' => EditSalesLead::class])
            ->filterTable('contact_date', ['from' => now()->subDays(2)->toDateString()])
            ->assertCanSeeTableRecords($lead->interactions()->where('summary', 'Contato recente')->get())
            ->assertCanNotSeeTableRecords($lead->interactions()->where('summary', 'Contato antigo')->get());
    }
}
