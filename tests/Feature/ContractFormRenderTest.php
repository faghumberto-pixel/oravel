<?php

namespace Tests\Feature;

use App\Models\DocumentSignature;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
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

    public function test_signature_link_opens_for_a_logged_in_user_of_another_tenant(): void
    {
        // Regressao (2026-10-01): o operador da Oravel, logado, abria o link de
        // assinatura de um cliente e via "No query results for model
        // [DocumentSignature]" por causa do escopo de tenant.
        $plan = Plan::create([
            'name' => 'Plano Outro Tenant', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);
        $cliente = Tenant::create(['name' => 'Cliente Alvo', 'slug' => 'cliente-alvo-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'trial']);
        $operadorTenant = Tenant::create(['name' => 'Empresa Operadora', 'slug' => 'operadora-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);

        $link = app(SignatureService::class)->generateSignatureLink($cliente, ['name' => 'Fulano', 'email' => 'f@x.com']);

        $operador = User::create([
            'name' => 'Operador', 'email' => 'op-'.uniqid().'@oravel.com.br', 'password' => bcrypt('teste123'),
            'tenant_id' => $operadorTenant->id,
        ]);
        $this->actingAs($operador);

        $this->get($link)->assertOk()->assertSee('Cliente Alvo')->assertDontSee('No query results');
    }

    public function test_signature_links_expire_in_five_days(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Prazo', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);
        $tenant = Tenant::create(['name' => 'Cliente Prazo', 'slug' => 'cliente-prazo-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'trial']);

        app(SignatureService::class)->generateSignatureLink($tenant, ['name' => 'Fulano', 'email' => 'f@x.com']);

        $signature = DocumentSignature::withoutGlobalScopes()->where('signable_id', $tenant->id)->sole();
        $this->assertEquals(5, (int) round(now()->diffInDays($signature->expires_at, false)));

        // Vencido: a tela pública mostra o aviso com os 5 dias.
        $signature->update(['expires_at' => now()->subMinute()]);
        $this->get(route('signature.sign', ['token' => $signature->token]))->assertSee('expirado após 5 dias');
    }
}
