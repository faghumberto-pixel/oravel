<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\SalesLeadResource\Pages\ListSalesLeads;
use App\Models\SalesLead;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Grid estilo planilha (pedido do usuario 2026-08-19: "editar direto na
 * celula, como Excel/Sheets") -- Empresa, Estagio, Valor Estimado e
 * Responsavel viraram colunas editaveis (TextInputColumn/SelectColumn) na
 * propria listagem, sem abrir a pagina de edicao.
 */
class SalesLeadInlineGridEditTest extends TestCase
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

        return $super;
    }

    public function test_can_edit_company_name_inline_in_the_grid(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $lead = SalesLead::create([
            'company_name' => 'Nome Antigo',
            'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
        ]);

        Livewire::test(ListSalesLeads::class)
            ->call('updateTableColumnState', 'company_name', $lead->getKey(), 'Nome Editado Inline');

        $this->assertSame('Nome Editado Inline', $lead->refresh()->company_name);
    }

    public function test_can_change_pipeline_stage_inline_in_the_grid(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $lead = SalesLead::create([
            'company_name' => 'Empresa Estagio',
            'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
        ]);

        Livewire::test(ListSalesLeads::class)
            ->call('updateTableColumnState', 'pipeline_stage', $lead->getKey(), SalesLead::STAGE_CONTATO_QUALIFICADO);

        $this->assertSame(SalesLead::STAGE_CONTATO_QUALIFICADO, $lead->refresh()->pipeline_stage);
    }

    public function test_can_change_estimated_contract_value_inline_in_the_grid(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $lead = SalesLead::create([
            'company_name' => 'Empresa Valor',
            'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
        ]);

        Livewire::test(ListSalesLeads::class)
            ->call('updateTableColumnState', 'estimated_contract_value', $lead->getKey(), '15000');

        $this->assertEquals(15000, $lead->refresh()->estimated_contract_value);
    }

    public function test_can_change_assigned_user_inline_in_the_grid(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $lead = SalesLead::create([
            'company_name' => 'Empresa Responsável',
            'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
        ]);

        Livewire::test(ListSalesLeads::class)
            ->call('updateTableColumnState', 'assigned_user_id', $lead->getKey(), $super->id);

        $this->assertSame($super->id, $lead->refresh()->assigned_user_id);
    }
}
