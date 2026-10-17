<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ContratoResource;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Entrega C/D: plano de vitrine por pacote (preço editado na Central) e checkout /assinar/{pacote}. */
class PacotesCheckoutTest extends TestCase
{
    use DatabaseTransactions;

    private function aVenda(string $codigo, float $preco = 300): Plan
    {
        $plano = Pacotes::planoDeVitrine($codigo);
        $plano->update(['base_price' => $preco, 'price' => $preco, 'is_active' => true]);

        return $plano->fresh();
    }

    private function payload(Plan $plan, array $extra = []): array
    {
        return $extra + [
            'plan_id' => $plan->id, 'company_name' => 'Empresa Pacote '.uniqid(), 'segment' => Client::NICHE_CONSTRUCAO_CIVIL,
            'equipment_types' => ['gerador'], 'cpf_cnpj' => '123.456.789-09', 'cep' => '13480-000', 'logradouro' => 'Rua A', 'numero' => '1',
            'bairro' => 'Centro', 'cidade' => 'Limeira', 'uf' => 'SP', 'admin_name' => 'Admin', 'admin_email' => 'adm-'.uniqid().'@oravel.com.br',
            'telefone' => '(19) 99999-9999', 'admin_password' => 'senha12345', 'terms_accepted' => '1',
        ];
    }

    public function test_showcase_plan_is_born_without_price_and_off_sale_and_nothing_overwrites_central_edits(): void
    {
        $plano = Pacotes::planoDeVitrine('manutencao');

        $this->assertSame('vitrine', $plano->campaign_tag);
        $this->assertSame('Oravel Manutenção', $plano->name);
        $this->assertFalse($plano->is_active);
        $this->assertEquals(0, $plano->price);

        // O operador define preço, ciclo, à venda e ajusta módulos na Central.
        $features = $plano->features;
        unset($features['tabela_clients']);
        $plano->update(['base_price' => 490, 'price' => 490, 'billing_cycle' => 'annual', 'is_active' => true, 'features' => $features]);

        $de_novo = Pacotes::planoDeVitrine('manutencao');
        $this->assertSame($plano->id, $de_novo->id, 'não duplica');
        $this->assertEquals(490, $de_novo->price);
        $this->assertSame('annual', $de_novo->billing_cycle);
        $this->assertTrue($de_novo->is_active);
        $this->assertArrayNotHasKey('tabela_clients', $de_novo->features, 'ajuste de módulos da Central preservado');

        $sincronizado = Pacotes::planoDeVitrine('manutencao', true);
        $this->assertArrayHasKey('tabela_clients', $sincronizado->features);
        $this->assertEquals(490, $sincronizado->price, 'sincronizar módulos não mexe no preço');
    }

    public function test_command_creates_one_plan_per_package_and_is_idempotent(): void
    {
        $this->artisan('pacotes:vitrine')->assertSuccessful();
        $this->artisan('pacotes:vitrine')->assertSuccessful();

        foreach (array_keys(Pacotes::opcoes()) as $codigo) {
            $this->assertSame(1, Plan::where('pacote', $codigo)->where('campaign_tag', 'vitrine')->count(), $codigo);
        }

        $this->artisan('pacotes:vitrine', ['--pacote' => 'crm'])->assertSuccessful();   // alias antigo
        $this->assertSame(1, Plan::where('pacote', 'vendas')->where('campaign_tag', 'vitrine')->count());
        $this->artisan('pacotes:vitrine', ['--pacote' => 'inexistente'])->assertFailed();
    }

    public function test_stable_package_link_only_sells_plans_that_are_on_sale_with_a_price(): void
    {
        $this->get('/assinar/manutencao')->assertRedirect('https://oravel.com.br/contato.php');   // sem vitrine

        $plano = Pacotes::planoDeVitrine('manutencao');
        $this->get('/assinar/manutencao')->assertRedirect('https://oravel.com.br/contato.php');   // sem preço/inativo

        $plano->update(['is_active' => true]);
        $this->get('/assinar/manutencao')->assertRedirect('https://oravel.com.br/contato.php');   // ativo mas sem preço

        $plano->update(['base_price' => 490, 'price' => 490]);
        $this->get('/assinar/manutencao')->assertOk()->assertSee('Oravel Manutenção')->assertSee('490,00')->assertSee('Manutenção de equipamentos');

        $this->get('/assinar/crm')->assertRedirect('https://oravel.com.br/contato.php');          // alias sem vitrine de 'crm' (a de vendas não existe)
        $this->get('/assinar/pendente')->assertOk();                                          // rota fixa não é engolida
        $this->get('/assinar/inexistente')->assertRedirect('https://oravel.com.br/contato.php');
    }

    public function test_uuid_link_still_works_and_inactive_plan_is_never_sold(): void
    {
        $plano = $this->aVenda('logistica');
        $this->get('/assinar?plano='.$plano->id)->assertOk()->assertSee('Oravel Logística')->assertSee('Logística e frota');

        $plano->update(['is_active' => false]);
        $this->get('/assinar?plano='.$plano->id)->assertRedirect('https://oravel.com.br/#planos');
        $this->post('/assinar', $this->payload($plano))->assertNotFound();
    }

    public function test_buying_each_package_creates_a_tenant_with_its_modules(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake(['sandbox.asaas.com/*/customers' => Http::response(['id' => 'cus_pacote'], 200)]);

        foreach (array_keys(Pacotes::opcoes()) as $codigo) {
            $plano = $this->aVenda($codigo);
            $dados = $this->payload($plano, ['forma_pagamento' => 'cartao', 'implantacao' => 'nao_contratar']);

            $this->post('/assinar', $dados)->assertRedirect();

            $tenant = Tenant::where('name', $dados['company_name'])->firstOrFail();
            $this->assertSame($plano->id, $tenant->plan_id);
            $this->assertSame($codigo, $tenant->pacote());
            $this->assertEquals(180, $tenant->mrr_value, 'valor cheio 300 no cartão recorrente: 40% de desconto');
            $this->assertTrue($tenant->plan->hasFeature('modulo_dashboard'), "{$codigo}: base comum");
        }
    }

    public function test_central_lets_the_operator_set_price_and_put_the_showcase_plan_on_sale(): void
    {
        $plano = Pacotes::planoDeVitrine('servicos');
        $super = User::create(['name' => 'Super', 'email' => (config('oravel.super_admins')[0] ?? 'faghumberto@gmail.com'), 'password' => bcrypt('x'), 'is_approved' => true]);
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        Livewire::test(ContratoResource\Pages\EditContrato::class, ['record' => $plano->getKey()])
            ->assertFormFieldIsVisible('is_active')
            ->fillForm(['base_price' => 790, 'billing_cycle' => 'quarterly', 'is_active' => true])
            ->call('save')->assertHasNoFormErrors();

        $plano->refresh();
        $this->assertEquals(790, $plano->price, 'o checkout lê price, espelhado de base_price');
        $this->assertTrue($plano->is_active);
        auth()->guard('web')->logout();   // /assinar é só para visitante
        $this->get('/assinar/servicos')->assertOk()->assertSee('790,00');

        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        // Contrato de cliente comum não mostra o interruptor e continua ativo.
        $cliente = Plan::create(['name' => 'Cliente X', 'pacote' => 'servicos', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        Livewire::test(ContratoResource\Pages\EditContrato::class, ['record' => $cliente->getKey()])
            ->assertFormFieldIsHidden('is_active');

        Livewire::test(ContratoResource\Pages\ListContratos::class)
            ->filterTable('vitrine', true)->assertCanSeeTableRecords([$plano])->assertCanNotSeeTableRecords([$cliente]);
    }

    public function test_site_plans_page_has_a_link_per_package_and_no_price(): void
    {
        $html = file_get_contents(base_path('public_html/planos/index.html'));

        foreach (array_keys(Pacotes::opcoes()) as $codigo) {
            $this->assertStringContainsString('href="https://app.oravel.com.br/assinar/'.$codigo.'"', $html, "link de {$codigo}");
        }

        $this->assertStringNotContainsString('R$', $html, 'preço fica só na Central/checkout');
        $this->assertStringContainsString('href="/planos/"', file_get_contents(base_path('public_html/inc/nav-fragment.html')));
    }

    public function test_central_action_creates_the_missing_showcase_plans_without_touching_existing_ones(): void
    {
        $super = User::create(['name' => 'Super', 'email' => (config('oravel.super_admins')[0] ?? 'faghumberto@gmail.com'), 'password' => bcrypt('x'), 'is_approved' => true]);
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $editado = $this->aVenda('estoque', 450);

        Livewire::test(ContratoResource\Pages\ListContratos::class)
            ->callAction('criarVitrines')
            ->assertNotified();

        foreach (array_keys(Pacotes::opcoes()) as $codigo) {
            $this->assertSame(1, Plan::where('pacote', $codigo)->where('campaign_tag', 'vitrine')->count(), $codigo);
        }
        $this->assertEquals(450, $editado->fresh()->price, 'vitrine já editada não muda');
        $this->assertTrue($editado->fresh()->is_active);
        $this->assertFalse(Pacotes::vitrine('vendas')->is_active, 'novas nascem fora de venda');

        // Segunda vez: nada a criar.
        Livewire::test(ContratoResource\Pages\ListContratos::class)->callAction('criarVitrines')->assertNotified('Todos os pacotes já têm plano de vitrine.');
        $this->assertSame(6, Plan::where('campaign_tag', 'vitrine')->count());
    }
}
