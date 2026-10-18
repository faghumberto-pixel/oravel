<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\OnboardingInicio;
use App\Models\OnboardingProgresso;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\Tenant;
use App\Support\Onboarding\OnboardingService;
use App\Support\Pacotes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Onboarding guiado (Serviços): detectores de conclusão por dados reais e estado por empresa. */
class OnboardingEstadoTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(string $pacote = 'servicos'): Tenant
    {
        $plano = Pacotes::planoDeVitrine($pacote);

        return Tenant::create(['name' => 'Empresa '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
    }

    private function proposta(Tenant $t, string $status, array $extra = []): PropostaComercial
    {
        return PropostaComercial::withoutGlobalScopes()->create($extra + ['tenant_id' => $t->id, 'status' => $status]);
    }

    private function servico(): OnboardingService
    {
        return app(OnboardingService::class);
    }

    public function test_only_configured_solutions_have_onboarding(): void
    {
        $this->assertSame('servicos', $this->servico()->solucaoDe($this->tenant('servicos')));
        $this->assertNull($this->servico()->solucaoDe($this->tenant('estoque')), 'Estoque ainda não tem onboarding');
        $this->assertNull($this->servico()->estado($this->tenant('logistica')));

        $personalizado = Plan::create(['name' => 'P'.uniqid(), 'price' => 1, 'is_active' => true, 'features' => []]);
        $t = Tenant::create(['name' => 'X', 'slug' => 'x-'.uniqid(), 'plan_id' => $personalizado->id, 'status' => 'active']);
        $this->assertNull($this->servico()->solucaoDe($t));
    }

    public function test_new_company_starts_on_step_one_with_the_estimated_time(): void
    {
        $e = $this->servico()->estado($this->tenant());

        $this->assertSame(3, $e['total']);
        $this->assertSame(0, $e['concluidos']);
        $this->assertSame(0, $e['atual']);
        $this->assertSame(25, $e['minutos_restantes']);
        $this->assertFalse($e['completo']);
        $this->assertSame(['importar_clientes', 'primeira_proposta', 'acompanhar_aceite'], array_column($e['passos'], 'chave'));
    }

    public function test_steps_complete_by_real_data_and_never_depend_on_a_click(): void
    {
        $t = $this->tenant();

        Client::create(['tenant_id' => $t->id, 'name' => 'Cliente A', 'cpf_cnpj' => '11.444.777/0001-61']);
        $e = $this->servico()->estado($t);
        $this->assertSame([true, false, false], array_column($e['passos'], 'concluido'));
        $this->assertSame(1, $e['atual']);
        $this->assertSame(15, $e['minutos_restantes']);

        $this->proposta($t, PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL);
        $this->assertSame([true, false, false], array_column($this->servico()->estado($t)['passos'], 'concluido'), 'ainda na revisão interna: não chegou ao cliente');

        $this->proposta($t, PropostaComercial::STATUS_APROVADA_INTERNA);
        $this->assertSame([true, true, false], array_column($this->servico()->estado($t)['passos'], 'concluido'));

        $this->proposta($t, PropostaComercial::STATUS_APROVADA_INTERNA, ['client_viewed_at' => now()]);
        $e = $this->servico()->estado($t);
        $this->assertTrue($e['completo']);
        $this->assertSame(100, $e['percentual']);
        $this->assertNull($e['atual']);
        $this->assertNotNull(OnboardingInicio::withoutGlobalScopes()->where('tenant_id', $t->id)->first()->concluido_em);
    }

    public function test_an_accepted_or_refused_proposal_also_counts_as_seen(): void
    {
        $t = $this->tenant();
        $this->proposta($t, PropostaComercial::STATUS_ACEITA_PELO_CLIENTE);

        $this->assertSame([false, true, true], array_column($this->servico()->estado($t)['passos'], 'concluido'));
    }

    public function test_a_completed_step_stays_completed_after_the_data_is_deleted(): void
    {
        $t = $this->tenant();
        $c = Client::create(['tenant_id' => $t->id, 'name' => 'Cliente A']);
        $this->servico()->estado($t);

        $c->forceDelete();

        $this->assertTrue($this->servico()->estado($t)['passos'][0]['concluido']);
        $this->assertSame(1, OnboardingProgresso::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
    }

    public function test_progress_is_isolated_between_companies_and_start_follows_the_signup(): void
    {
        $a = $this->tenant();
        $b = $this->tenant();
        Client::create(['tenant_id' => $a->id, 'name' => 'Só da empresa A']);

        $this->assertTrue($this->servico()->estado($a)['passos'][0]['concluido']);
        $this->assertFalse($this->servico()->estado($b)['passos'][0]['concluido']);
        $this->assertTrue($this->servico()->estado($a)['iniciado_em']->equalTo($a->created_at));
    }

    public function test_first_access_and_dismiss_are_recorded_once(): void
    {
        $t = $this->tenant();
        $this->assertTrue($this->servico()->pendente($t));

        $this->servico()->registrarPrimeiroAcesso($t);
        $primeiro = $this->servico()->estado($t)['primeiro_acesso_em'];
        $this->assertNotNull($primeiro);

        $this->travel(5)->minutes();
        $this->servico()->registrarPrimeiroAcesso($t);
        $this->assertTrue($primeiro->equalTo($this->servico()->estado($t)['primeiro_acesso_em']));

        $this->servico()->dispensar($t);
        $this->assertFalse($this->servico()->pendente($t));
    }
}
