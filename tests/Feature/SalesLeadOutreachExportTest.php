<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\SalesLeadResource\Pages\EditSalesLead;
use App\Filament\Central\Resources\SalesLeadResource\Pages\ListSalesLeads;
use App\Filament\Exports\SalesLeadExporter;
use App\Models\SalesLead;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * outreach_email_draft (rascunho de e-mail de prospeccao D3, campo novo em
 * SalesLead) + botao Exportar em ListSalesLeads -- pedido do usuario
 * 2026-08-04: guardar o texto de e-mail junto do lead, exportavel em CSV.
 */
class SalesLeadOutreachExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mesmo fix de PrintExportActionsTest: config('queue.batching.database')
     * ignora o DB_CONNECTION forçado pra sqlite em teste, apontando o
     * Bus::batch() do ExportAction pra uma conexão nunca migrada (sem
     * job_batches). Alinha pra mesma conexão real usada pelos dados.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.batching.database' => config('database.default')]);
    }

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

    public function test_outreach_email_draft_is_saveable_and_editable_on_the_lead_form(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $lead = SalesLead::create([
            'company_name' => 'Empresa Draft', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
        ]);

        $draft = "Assunto: Teste D3\n\nOlá, tudo bem?";

        Livewire::test(EditSalesLead::class, ['record' => $lead->id])
            ->fillForm(['outreach_email_draft' => $draft])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($draft, $lead->fresh()->outreach_email_draft);
    }

    public function test_export_action_is_available_on_the_leads_list(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        SalesLead::create([
            'company_name' => 'Empresa Export', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
            'outreach_email_draft' => 'Texto do e-mail de prospecção.',
        ]);

        Livewire::test(ListSalesLeads::class)
            ->callAction('export')
            ->assertHasNoActionErrors();

        $this->assertSame(1, Export::where('exporter', SalesLeadExporter::class)->count());
    }
}
