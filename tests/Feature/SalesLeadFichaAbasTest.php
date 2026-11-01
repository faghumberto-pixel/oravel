<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\SalesLeadResource\Pages\EditSalesLead;
use App\Models\SalesLead;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Ficha do lead com abas unicas (Dados | Histórico | Contatos | Agenda), resumo no topo e registro rapido de contato. */
class SalesLeadFichaAbasTest extends TestCase
{
    use DatabaseTransactions;

    private function lead(): SalesLead
    {
        config(['oravel.super_admins' => ['equipe@oravel.test']]);
        Filament::setCurrentPanel(Filament::getPanel('central'));
        $this->actingAs(User::factory()->create(['email' => 'equipe@oravel.test']));

        return SalesLead::create(['company_name' => 'Ficha X', 'phone' => '(19) 99999-0000', 'email' => 'x@y.com', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO]);
    }

    public function test_ficha_mostra_abas_unicas_e_resumo_no_topo(): void
    {
        $lead = $this->lead();
        $html = Livewire::test(EditSalesLead::class, ['record' => $lead->getKey()])->assertSuccessful()->html();

        foreach (['Dados', 'Histórico', 'Contatos', 'Agenda', 'Empresa e contato', 'Venda', 'Etapa: Prospecção', 'Nunca contatado', '(19) 99999-0000'] as $t) {
            $this->assertStringContainsString($t, html_entity_decode($html), $t);
        }
    }

    public function test_registrar_contato_pela_ficha(): void
    {
        $lead = $this->lead();
        Livewire::test(EditSalesLead::class, ['record' => $lead->getKey()])
            ->callAction('registrar_contato', ['channel' => 'telefone', 'contact_date' => now()->toDateTimeString(), 'summary' => 'Liguei.', 'next_followup_date' => now()->addDays(2)->toDateString()])
            ->assertHasNoActionErrors();

        $lead->refresh();
        $this->assertSame(1, $lead->interactions()->count());
        $this->assertNotNull($lead->last_interaction_at);
        $this->assertSame(now()->addDays(2)->toDateString(), $lead->next_followup_date->toDateString());
    }

    public function test_campos_continuam_salvando(): void
    {
        $lead = $this->lead();
        Livewire::test(EditSalesLead::class, ['record' => $lead->getKey()])
            ->fillForm(['company_name' => 'Ficha Y', 'critical_pain' => 'Manutenção parada', 'segment' => null])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Ficha Y', $lead->fresh()->company_name);
        $this->assertSame('Manutenção parada', $lead->fresh()->critical_pain);
    }
}
