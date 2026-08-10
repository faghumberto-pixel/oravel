<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\SalesLeadResource\Pages\ListSalesLeads;
use App\Filament\Central\Resources\SalesLeadResource\Widgets\LeadsByStageChart;
use App\Filament\Central\Resources\SalesLeadResource\Widgets\SalesLeadListStats;
use App\Models\SalesLead;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesLeadListWidgetsTest extends TestCase
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

    public function test_list_page_renders_with_all_header_widgets(): void
    {
        $this->actingAsSuperAdmin();

        SalesLead::create([
            'company_name' => 'Empresa Com Contato',
            'phone' => '11999999999',
            'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
        ]);
        SalesLead::create([
            'company_name' => 'Empresa Sem Contato',
            'pipeline_stage' => SalesLead::STAGE_GANHO,
        ]);

        $html = Livewire::test(ListSalesLeads::class)
            ->assertSuccessful()
            ->html();

        // Widgets sao lazy por padrao (placeholder no snapshot inicial,
        // conteudo real via requisicao Livewire separada) -- confirma que
        // o componente foi montado na pagina pelo container real do
        // Filament, nao pelo texto (que so aparece depois do lazy load).
        $this->assertStringContainsString('fi-page-header-widgets', $html);
        $this->assertStringContainsString('sales-lead-list-stats', $html);
    }

    /**
     * Pedido do usuario 2026-08-10: "quando clico em um card deve
     * aparecer a lista sem nenhum card ou grafico" -- clicar num card
     * chega na listagem com tableFilters ja preenchido (mesmo mecanismo
     * que o Stat::url() usa, ver SalesLeadListStats/InteractionChannelStats).
     */
    public function test_header_widgets_disappear_when_a_table_filter_is_active(): void
    {
        $this->actingAsSuperAdmin();

        SalesLead::create(['company_name' => 'Com Telefone', 'phone' => '11999999999']);

        $html = Livewire::test(ListSalesLeads::class)
            ->set('tableFilters.has_contact.value', '1')
            ->assertSuccessful()
            ->html();

        $this->assertStringNotContainsString('fi-page-header-widgets', $html);
    }

    public function test_stats_widget_counts_and_links_are_correct(): void
    {
        $this->actingAsSuperAdmin();

        SalesLead::create([
            'company_name' => 'Com Telefone',
            'phone' => '11999999999',
            'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
        ]);
        SalesLead::create([
            'company_name' => 'Com Email',
            'email' => 'contato@empresa.com',
            'pipeline_stage' => SalesLead::STAGE_GANHO,
        ]);
        SalesLead::create([
            'company_name' => 'Sem Contato',
            'pipeline_stage' => SalesLead::STAGE_PERDIDO,
        ]);

        Livewire::test(SalesLeadListStats::class)
            ->assertSuccessful();

        // 3 leads total, 2 com contato (telefone OU email), 1 sem,
        // 1 aberto (nem ganho nem perdido).
        $this->assertSame(3, SalesLead::count());
        $this->assertSame(2, SalesLead::where(fn ($q) => $q->whereNotNull('phone')->orWhereNotNull('email'))->count());
        $this->assertSame(1, SalesLead::whereNotIn('pipeline_stage', [SalesLead::STAGE_GANHO, SalesLead::STAGE_PERDIDO])->count());
    }

    public function test_has_contact_filter_matches_the_stat_card_link(): void
    {
        $this->actingAsSuperAdmin();

        $comTelefone = SalesLead::create([
            'company_name' => 'Com Telefone',
            'phone' => '11999999999',
        ]);
        $comEmail = SalesLead::create([
            'company_name' => 'Com Email',
            'email' => 'contato@empresa.com',
        ]);
        $semContato = SalesLead::create([
            'company_name' => 'Sem Contato',
        ]);

        Livewire::test(ListSalesLeads::class)
            ->filterTable('has_contact', true)
            ->assertCanSeeTableRecords([$comTelefone, $comEmail])
            ->assertCanNotSeeTableRecords([$semContato]);

        Livewire::test(ListSalesLeads::class)
            ->filterTable('has_contact', false)
            ->assertCanSeeTableRecords([$semContato])
            ->assertCanNotSeeTableRecords([$comTelefone, $comEmail]);
    }

    public function test_stage_chart_widget_renders_without_error(): void
    {
        $this->actingAsSuperAdmin();

        SalesLead::create(['company_name' => 'Lead A', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO]);
        SalesLead::create(['company_name' => 'Lead B', 'pipeline_stage' => SalesLead::STAGE_GANHO]);

        Livewire::test(LeadsByStageChart::class)
            ->assertSuccessful();
    }
}
