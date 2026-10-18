<?php

namespace Tests\Feature;

use App\Filament\Pages\CrmFunil;
use App\Filament\Pages\ImportarPlanilhaCrm;
use App\Filament\Pages\PrimeirosPassos;
use App\Mail\OnboardingLembreteMail;
use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use App\Models\CrmProposta;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OnboardingLembretes;
use App\Support\AppProfile;
use App\Support\Onboarding\OnboardingService;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** Onboarding da solução Vendas: 3 leads, primeiro contato registrado e primeira proposta enviada, por dados reais. */
class OnboardingVendasTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function empresa(): Tenant
    {
        $plano = Pacotes::planoDeVitrine('vendas');
        $plano->update(['is_active' => true]);

        return Tenant::create(['name' => 'Vendedora '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
    }

    private function lead(Tenant $t, int $n = 1): CrmLead
    {
        return CrmLead::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'name' => "Lead {$n}"]);
    }

    private function servico(): OnboardingService
    {
        return app(OnboardingService::class);
    }

    public function test_the_steps_complete_by_real_data(): void
    {
        $t = $this->empresa();
        $e = $this->servico()->estado($t);
        $this->assertSame(['cadastrar_leads', 'registrar_contato', 'enviar_proposta'], array_column($e['passos'], 'chave'));
        $this->assertSame(25, $e['minutos_restantes']);

        $a = $this->lead($t, 1);
        $this->lead($t, 2);
        $this->assertFalse($this->servico()->estado($t)['passos'][0]['concluido'], 'precisa de 3 leads');

        $this->lead($t, 3);
        $this->assertSame([true, false, false], array_column($this->servico()->estado($t)['passos'], 'concluido'));

        $vendedor = User::create(['name' => 'Vendedor', 'email' => 'v-'.uniqid().'@vendas.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        CrmLeadInteraction::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'crm_lead_id' => $a->id, 'user_id' => $vendedor->id, 'channel' => 'telefone', 'direcao' => 'saida', 'contact_date' => now(), 'summary' => 'Primeira ligação', 'stage_at_time' => 'novo']);
        $this->assertSame([true, true, false], array_column($this->servico()->estado($t)['passos'], 'concluido'));

        CrmProposta::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'crm_lead_id' => $a->id, 'titulo' => 'Rascunho', 'status' => 'rascunho']);
        $this->assertFalse($this->servico()->estado($t)['passos'][2]['concluido'], 'proposta só em rascunho ainda não foi enviada');

        CrmProposta::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'crm_lead_id' => $a->id, 'titulo' => 'Enviada', 'status' => 'enviada', 'enviada_em' => now()]);
        $e = $this->servico()->estado($t);
        $this->assertTrue($e['completo']);
        $this->assertStringContainsString('primeira proposta', $e['conclusao']);
    }

    public function test_each_company_counts_only_its_own_leads(): void
    {
        $a = $this->empresa();
        $b = $this->empresa();
        foreach ([1, 2, 3] as $n) {
            $this->lead($a, $n);
        }

        $this->assertTrue($this->servico()->estado($a)['passos'][0]['concluido']);
        $this->assertFalse($this->servico()->estado($b)['passos'][0]['concluido']);
    }

    public function test_screen_shows_the_sales_steps_with_direct_buttons(): void
    {
        $t = $this->empresa();
        $u = User::create(['name' => 'Paulo Reis', 'email' => 'p-'.uniqid().'@vendas.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        $this->actingAs($u);

        $this->assertSame(PrimeirosPassos::getUrl(), AppProfile::homeUrl($u, AppProfile::ADMIN));
        Livewire::test(PrimeirosPassos::class)->assertSuccessful()->assertSee('Bem-vindo, Paulo')
            ->assertSee('Faltam 3 passos para enviar sua primeira proposta. Leva cerca de 25 minutos.')
            ->assertSee('Cadastre seus leads')->assertSee('Registre seu primeiro contato')->assertSee('Envie sua primeira proposta')
            ->assertSee('Importar planilha')->assertSee(ImportarPlanilhaCrm::getUrl(), false)->assertSee('Cadastrar lead')
            ->assertSee(CrmFunil::getUrl(), false);
    }

    public function test_reminder_text_follows_the_solution(): void
    {
        Mail::fake();
        $t = $this->empresa();
        $u = User::create(['name' => 'Adm', 'email' => 'adm-'.uniqid().'@vendas.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        $t->forceFill(['created_at' => Carbon::parse('2026-10-20 08:00', 'America/Sao_Paulo')->subDays(2)])->save();

        app(OnboardingLembretes::class)->executar(Carbon::parse('2026-10-20 10:00', 'America/Sao_Paulo'));

        Mail::assertSent(OnboardingLembreteMail::class, fn (OnboardingLembreteMail $m) => str_contains($m->assunto.implode(' ', $m->paragrafos), 'Cadastre seus leads'));
    }
}
