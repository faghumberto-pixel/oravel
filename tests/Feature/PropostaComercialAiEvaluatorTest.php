<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AnthropicApiClient;
use App\Services\PropostaComercialAiEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropostaComercialAiEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private function makeProposta(): PropostaComercial
    {
        $plan = Plan::create([
            'name' => 'Plano AI Eval '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_proposta_comercial'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant AI Eval '.uniqid(), 'slug' => 'tenant-ai-eval-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente AI Eval']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeiras']);

        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
            'terms' => 'Pagamento em 30 dias.',
        ]);
        PropostaComercialItem::create([
            'tenant_id' => $tenant->id, 'proposta_comercial_id' => $proposta->id,
            'type' => PropostaComercialItem::TYPE_EQUIPAMENTO, 'asset_category_id' => $category->id,
            'description' => 'Empilhadeira 2.5t', 'quantity' => 1, 'unit_price' => 1500,
        ]);

        return $proposta->fresh();
    }

    public function test_evaluate_persiste_o_parecer_da_ia(): void
    {
        $proposta = $this->makeProposta();

        $mockResponse = json_encode([
            'risco_coerencia' => ['nota' => 4, 'comentario' => 'Coerente.'],
            'qualidade_clareza' => ['nota' => 5, 'comentario' => 'Muito claro.'],
            'probabilidade_fechamento' => ['nota' => 3, 'comentario' => 'Provável.'],
        ]);

        $mockClient = \Mockery::mock(AnthropicApiClient::class);
        $mockClient->shouldReceive('send')
            ->once()
            ->andReturn(['ok' => true, 'text' => $mockResponse, 'error' => null]);
        $mockClient->shouldReceive('parseJson')
            ->once()
            ->with($mockResponse)
            ->andReturn(json_decode($mockResponse, true));

        $evaluator = new PropostaComercialAiEvaluator($mockClient);
        $result = $evaluator->evaluate($proposta);

        $this->assertSame(4, $result['risco_coerencia']['nota']);
        $proposta->refresh();
        $this->assertNotNull($proposta->ai_evaluated_at);
        $this->assertSame(5, $proposta->ai_evaluation['qualidade_clareza']['nota']);
    }

    public function test_evaluate_lanca_excecao_quando_a_api_falha(): void
    {
        $proposta = $this->makeProposta();

        $mockClient = \Mockery::mock(AnthropicApiClient::class);
        $mockClient->shouldReceive('send')
            ->once()
            ->andReturn(['ok' => false, 'text' => null, 'error' => 'Timeout']);

        $evaluator = new PropostaComercialAiEvaluator($mockClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Timeout');
        $evaluator->evaluate($proposta);
    }

    public function test_evaluate_lanca_excecao_quando_resposta_nao_e_json_valido(): void
    {
        $proposta = $this->makeProposta();

        $mockClient = \Mockery::mock(AnthropicApiClient::class);
        $mockClient->shouldReceive('send')
            ->once()
            ->andReturn(['ok' => true, 'text' => 'texto sem json', 'error' => null]);
        $mockClient->shouldReceive('parseJson')
            ->once()
            ->andReturn(null);

        $evaluator = new PropostaComercialAiEvaluator($mockClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('formato reconhecível');
        $evaluator->evaluate($proposta);
    }

    public function test_reavaliar_sobrescreve_o_parecer_anterior(): void
    {
        $proposta = $this->makeProposta();
        $proposta->update(['ai_evaluation' => ['antigo' => true], 'ai_evaluated_at' => now()->subDay()]);

        $mockResponse = json_encode([
            'risco_coerencia' => ['nota' => 2, 'comentario' => 'Novo parecer.'],
            'qualidade_clareza' => ['nota' => 2, 'comentario' => 'Novo.'],
            'probabilidade_fechamento' => ['nota' => 2, 'comentario' => 'Novo.'],
        ]);

        $mockClient = \Mockery::mock(AnthropicApiClient::class);
        $mockClient->shouldReceive('send')->once()->andReturn(['ok' => true, 'text' => $mockResponse, 'error' => null]);
        $mockClient->shouldReceive('parseJson')->once()->andReturn(json_decode($mockResponse, true));

        $evaluator = new PropostaComercialAiEvaluator($mockClient);
        $evaluator->evaluate($proposta);

        $proposta->refresh();
        $this->assertArrayNotHasKey('antigo', $proposta->ai_evaluation);
        $this->assertSame(2, $proposta->ai_evaluation['risco_coerencia']['nota']);
    }
}
