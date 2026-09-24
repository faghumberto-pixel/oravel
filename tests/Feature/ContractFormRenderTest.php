<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\SignatureService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ContractFormRenderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_contract_signature_form_renders_full_contract(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Render Teste', 'price' => 199, 'base_price' => 199, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);

        $tenant = Tenant::create([
            'name' => 'Empresa Render Teste', 'slug' => 'empresa-render-teste-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'trial', 'cpf_cnpj' => '123.456.789-01',
            'mrr_value' => 199,
        ]);

        $link = app(SignatureService::class)->generateSignatureLink($tenant, [
            'name' => 'Fulano', 'email' => 'fulano@oravel.com.br',
        ]);

        $response = $this->get($link);

        $response->assertOk();
        $response->assertSee('Contrato de Assinatura');
        $response->assertSee('Termos e Condições');
        $response->assertSee('Inadimplência e bloqueio de acesso');
        $response->assertSee('Imprimir contrato');
        $response->assertSee('contract-mode', false);
        // O fundo lilás original era um linear-gradient roxo no `body`; o
        // mesmo tom de roxo (#667eea) continua legitimamente em botões/abas
        // de destaque, então o que importa checar é o FUNDO da página em
        // si, que precisa ser o cinza neutro novo.
        $response->assertSee('background: #eef0f3', false);
    }
}
