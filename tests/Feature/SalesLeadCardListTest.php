<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\SalesLeadResource\Pages\ListSalesLeads;
use App\Models\SalesLead;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Lista do CRM Comercial em cartoes: cartoes de etapa clicaveis, busca e registro rapido de contato. */
class SalesLeadCardListTest extends TestCase
{
    use DatabaseTransactions;

    private function entrar(): User
    {
        config(['oravel.super_admins' => ['equipe@oravel.test']]);
        Filament::setCurrentPanel(Filament::getPanel('central'));
        $u = User::factory()->create(['email' => 'equipe@oravel.test']);
        $this->actingAs($u);

        return $u;
    }

    public function test_cartao_de_etapa_filtra_a_lista_e_mostra_contagem(): void
    {
        $this->entrar();
        $a = SalesLead::create(['company_name' => 'Lead Prospeccao', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO]);
        $b = SalesLead::create(['company_name' => 'Lead Proposta', 'pipeline_stage' => SalesLead::STAGE_PROPOSTA_ENVIADA]);

        $c = Livewire::test(ListSalesLeads::class)->assertCanSeeTableRecords([$a, $b]);
        $totais = collect($c->instance()->cards())->pluck('total', 'chave');
        $this->assertGreaterThanOrEqual(1, $totais[SalesLead::STAGE_PROPOSTA_ENVIADA]);

        $c->call('filtrarCard', SalesLead::STAGE_PROPOSTA_ENVIADA)
            ->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a])
            ->call('filtrarCard', 'todos')
            ->assertCanSeeTableRecords([$a, $b]);
    }

    public function test_registrar_contato_cria_interacao_e_atualiza_ultimo_contato_e_retorno(): void
    {
        $this->entrar();
        $lead = SalesLead::create(['company_name' => 'Lead Contato', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO]);

        Livewire::test(ListSalesLeads::class)->callTableAction('registrar_contato', $lead, [
            'channel' => 'whatsapp', 'contact_date' => now()->toDateTimeString(), 'summary' => 'Falei com o sócio.', 'next_followup_date' => now()->addDays(3)->toDateString(),
        ])->assertHasNoTableActionErrors();

        $lead->refresh();
        $this->assertSame(1, $lead->interactions()->count());
        $this->assertNotNull($lead->last_interaction_at);
        $this->assertSame(now()->addDays(3)->toDateString(), $lead->next_followup_date->toDateString());
        $this->assertNull($lead->blockerForNextStage());
    }

    public function test_filtros_sem_contato_e_retorno_vencido(): void
    {
        $this->entrar();
        $velho = SalesLead::create(['company_name' => 'Sem Contato', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO]);
        $novo = SalesLead::create(['company_name' => 'Contato Recente', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO, 'last_interaction_at' => now()]);
        $venc = SalesLead::create(['company_name' => 'Retorno Vencido', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO, 'last_interaction_at' => now(), 'next_followup_date' => now()->subDay()]);

        Livewire::test(ListSalesLeads::class)
            ->filterTable('sem_contato', true)->assertCanSeeTableRecords([$velho])->assertCanNotSeeTableRecords([$novo])
            ->resetTableFilters()->filterTable('retorno_vencido', true)->assertCanSeeTableRecords([$venc])->assertCanNotSeeTableRecords([$novo]);
    }
}
