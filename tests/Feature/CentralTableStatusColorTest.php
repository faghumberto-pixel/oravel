<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\SalesLeadResource\Pages\ListSalesLeads;
use App\Filament\Central\Resources\TenantResource\Pages\ListTenants;
use App\Models\Plan;
use App\Models\SalesLead;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pedido do usuario 2026-08-04: "tudo branco, sem distinguir o que esta em
 * prospeccao, cancelado etc" na listagem do painel Central -- adicionado
 * recordClasses() (borda lateral colorida por status) em SalesLeadResource
 * e TenantResource, alem do badge que ja existia. Este teste garante que a
 * classe de cor certa realmente sai no HTML renderizado, nao so' que o
 * codigo compila.
 */
class CentralTableStatusColorTest extends TestCase
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

    public function test_sales_lead_rows_get_a_border_class_matching_their_stage(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $prospeccao = SalesLead::create([
            'company_name' => 'Empresa Prospecção', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
        ]);
        $perdido = SalesLead::create([
            'company_name' => 'Empresa Perdida', 'pipeline_stage' => SalesLead::STAGE_PERDIDO,
        ]);

        $html = Livewire::test(ListSalesLeads::class)->html();

        // Cada estagio tem uma cor de borda diferente (CrmPalette::stage) --
        // prospeccao e perdido nao podem sair com a mesma classe, senao a
        // "borda colorida" nao esta de fato diferenciando nada. Prospecção
        // é azul desde 2026-08-05 (pedido do usuário), era slate antes.
        $this->assertStringContainsString('border-blue-600', $html);
        $this->assertStringContainsString('border-red-600', $html);
    }

    /**
     * Pedido do usuario 2026-08-05: fundo suave (baixa opacidade) por
     * estagio na linha inteira, empresa em laranja, texto do estagio em
     * negrito -- alem da borda lateral ja existente.
     */
    public function test_sales_lead_rows_get_a_soft_background_matching_their_stage(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        SalesLead::create([
            'company_name' => 'Empresa Prospecção Fundo', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
        ]);
        SalesLead::create([
            'company_name' => 'Empresa Perdida Fundo', 'pipeline_stage' => SalesLead::STAGE_PERDIDO,
        ]);

        $html = Livewire::test(ListSalesLeads::class)->html();

        $this->assertStringContainsString('bg-blue-50', $html);
        $this->assertStringContainsString('bg-red-50', $html);
    }

    public function test_company_name_is_rendered_in_orange(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        SalesLead::create([
            'company_name' => 'Empresa Laranja', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO,
        ]);

        Livewire::test(ListSalesLeads::class)
            ->assertSee('Empresa Laranja')
            ->assertSuccessful();

        // crmOrange é registrado como Color::Orange no CentralPanelProvider
        // -- confirma que a cor do texto realmente sai como laranja no HTML.
        // Filament resolve cor customizada via classe "fi-color-crmOrange" +
        // custom property CSS inline (--c-600:var(--crmOrange-600)), não
        // uma classe "text-crmOrange-600" literal.
        $html = Livewire::test(ListSalesLeads::class)->html();
        $this->assertStringContainsString('fi-color-crmOrange', $html);
        $this->assertStringContainsString('--c-600:var(--crmOrange-600)', $html);
    }

    public function test_pipeline_stage_filters_exist_for_every_column(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        Livewire::test(ListSalesLeads::class)
            ->assertTableFilterExists('pipeline_stage')
            ->assertTableFilterExists('segment')
            ->assertTableFilterExists('assigned_user_id')
            ->assertTableFilterExists('estimated_contract_value')
            ->assertTableFilterExists('last_interaction_at');
    }

    public function test_tenant_rows_get_a_border_class_matching_their_status(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $plan = Plan::create([
            'name' => 'Plano Cor '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
        ]);

        Tenant::create([
            'name' => 'Tenant Ativo', 'slug' => 'tenant-ativo-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        Tenant::create([
            'name' => 'Tenant Suspenso', 'slug' => 'tenant-suspenso-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'suspended',
        ]);

        $html = Livewire::test(ListTenants::class)->html();

        $this->assertStringContainsString('border-emerald-600', $html);
        $this->assertStringContainsString('border-red-600', $html);
    }
}
