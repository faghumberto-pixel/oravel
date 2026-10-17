<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Pacotes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Entrega C: plano de vitrine por pacote e checkout /assinar vendendo cada pacote. */
class PacotesCheckoutTest extends TestCase
{
    use DatabaseTransactions;

    private function payload(Plan $plan, array $extra = []): array
    {
        return $extra + [
            'plan_id' => $plan->id, 'company_name' => 'Empresa Pacote '.uniqid(), 'segment' => Client::NICHE_CONSTRUCAO_CIVIL,
            'equipment_types' => ['gerador'], 'cpf_cnpj' => '123.456.789-09', 'cep' => '13480-000', 'logradouro' => 'Rua A', 'numero' => '1',
            'bairro' => 'Centro', 'cidade' => 'Limeira', 'uf' => 'SP', 'admin_name' => 'Admin', 'admin_email' => 'adm-'.uniqid().'@oravel.com.br',
            'telefone' => '(19) 99999-9999', 'admin_password' => 'senha12345', 'terms_accepted' => '1',
        ];
    }

    public function test_showcase_plan_is_inactive_without_price_and_never_sold_at_zero(): void
    {
        $plano = Pacotes::planoDeVitrine('manutencao');

        $this->assertSame('manutencao', $plano->pacote);
        $this->assertSame('vitrine', $plano->campaign_tag);
        $this->assertSame('Oravel Manutenção', $plano->name);
        $this->assertFalse($plano->is_active);

        $this->get('/assinar?plano='.$plano->id)->assertRedirect('https://oravel.com.br/#planos');
    }

    public function test_showcase_plan_with_price_carries_the_package_modules_and_is_idempotent(): void
    {
        config(['pacotes.pacotes.servicos.preco_base' => 790, 'pacotes.pacotes.servicos.ciclo' => 'quarterly']);

        $a = Pacotes::planoDeVitrine('servicos');
        $b = Pacotes::planoDeVitrine('servicos');

        $this->assertSame($a->id, $b->id, 'não duplica');
        $this->assertTrue($b->is_active);
        $this->assertEquals(790, $b->price);
        $this->assertSame('quarterly', $b->billing_cycle);
        $this->assertSame(Pacotes::features('servicos'), array_keys($b->features));
        $this->assertTrue($b->hasFeature('tabela_quotes'));
        $this->assertFalse($b->hasFeature('tabela_solicitacao_locacao'));

        // Preço mudou no config → o mesmo plano é atualizado.
        config(['pacotes.pacotes.servicos.preco_base' => 890]);
        $this->assertEquals(890, Pacotes::planoDeVitrine('servicos')->price);
        $this->assertSame(1, Plan::where('pacote', 'servicos')->where('campaign_tag', 'vitrine')->count());
    }

    public function test_command_creates_one_plan_per_package_with_checkout_links(): void
    {
        config(['pacotes.pacotes.vendas.preco_base' => 199]);

        $this->artisan('pacotes:vitrine')->assertSuccessful();

        foreach (array_keys(Pacotes::opcoes()) as $codigo) {
            $this->assertSame(1, Plan::where('pacote', $codigo)->where('campaign_tag', 'vitrine')->count(), $codigo);
        }
        $vendas = Plan::where('pacote', 'vendas')->where('campaign_tag', 'vitrine')->firstOrFail();
        $this->assertTrue($vendas->is_active);

        $this->artisan('pacotes:vitrine', ['--pacote' => 'crm'])->assertSuccessful();   // alias antigo
        $this->assertSame(1, Plan::where('pacote', 'vendas')->where('campaign_tag', 'vitrine')->count());

        $this->artisan('pacotes:vitrine', ['--pacote' => 'inexistente'])->assertFailed();
    }

    public function test_checkout_form_shows_the_package_description(): void
    {
        config(['pacotes.pacotes.logistica.preco_base' => 350]);
        $plano = Pacotes::planoDeVitrine('logistica');

        $this->get('/assinar?plano='.$plano->id)->assertOk()->assertSee('Oravel Logística')->assertSee('Logística e frota');
    }

    public function test_buying_each_package_creates_a_tenant_with_exactly_its_modules_and_entry(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake(['sandbox.asaas.com/*/customers' => Http::response(['id' => 'cus_pacote'], 200)]);

        foreach (['manutencao', 'servicos', 'vendas', 'estoque', 'logistica', 'locadora'] as $codigo) {
            config(["pacotes.pacotes.{$codigo}.preco_base" => 300]);
            $plano = Pacotes::planoDeVitrine($codigo);
            $dados = $this->payload($plano);

            $this->post('/assinar', $dados)->assertRedirect();

            $tenant = Tenant::where('name', $dados['company_name'])->firstOrFail();
            $this->assertSame($plano->id, $tenant->plan_id);
            $this->assertSame($codigo, $tenant->pacote());
            $this->assertEquals(300, $tenant->mrr_value);
            $this->assertTrue($tenant->plan->hasFeature('modulo_dashboard'), "{$codigo}: base comum");
        }
    }

    public function test_inactive_plan_cannot_be_purchased_even_by_posting_its_id(): void
    {
        $plano = Pacotes::planoDeVitrine('estoque');   // sem preço = inativo
        $dados = $this->payload($plano);

        $this->post('/assinar', $dados)->assertNotFound();
        $this->assertNull(Tenant::where('name', $dados['company_name'])->first());
    }
}
