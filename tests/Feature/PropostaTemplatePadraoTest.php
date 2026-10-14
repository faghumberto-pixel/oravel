<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialTemplate;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * O template marcado como Padrao nunca era aplicado: fillFromTemplate() nao
 * tinha nenhum chamador (achado na conferencia da aula, 14/10/2026).
 */
class PropostaTemplatePadraoTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Tpl '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_proposta_comercial'],
        ]);

        return Tenant::create(['name' => 'Tenant Tpl '.uniqid(), 'slug' => 'tenant-tpl-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function template(Tenant $tenant, array $extra = []): PropostaComercialTemplate
    {
        return PropostaComercialTemplate::create(array_merge([
            'tenant_id' => $tenant->id, 'name' => 'Padrão '.uniqid(), 'is_default' => true, 'is_active' => true,
            'default_terms' => 'Pagamento em 30 dias.', 'cabecalho' => 'Locadora Teste',
            'default_valid_days' => 10, 'campos' => [['titulo' => 'Garantia', 'texto' => '90 dias']],
        ], $extra));
    }

    public function test_new_proposal_gets_the_default_template(): void
    {
        $tenant = $this->tenant();
        $this->template($tenant);

        $proposta = PropostaComercial::create(['tenant_id' => $tenant->id]);

        $this->assertSame('Pagamento em 30 dias.', $proposta->terms);
        $this->assertSame('Locadora Teste', $proposta->cabecalho);
        $this->assertSame('Garantia', $proposta->campos[0]['titulo']);
        $this->assertSame(now()->addDays(10)->toDateString(), $proposta->valid_until->toDateString());
    }

    public function test_explicit_terms_are_not_overwritten(): void
    {
        $tenant = $this->tenant();
        $this->template($tenant);

        $proposta = PropostaComercial::create(['tenant_id' => $tenant->id, 'terms' => 'Termos próprios']);

        $this->assertSame('Termos próprios', $proposta->terms);
        $this->assertNull($proposta->cabecalho);
    }

    public function test_inactive_or_non_default_template_is_ignored(): void
    {
        $tenant = $this->tenant();
        $this->template($tenant, ['is_default' => false]);
        $this->template($tenant, ['is_active' => false]);

        $proposta = PropostaComercial::create(['tenant_id' => $tenant->id]);

        $this->assertNull($proposta->terms);
    }

    public function test_default_template_of_another_tenant_is_not_used(): void
    {
        $this->template($this->tenant());
        $outro = $this->tenant();

        $proposta = PropostaComercial::create(['tenant_id' => $outro->id]);

        $this->assertNull($proposta->terms);
    }
}
