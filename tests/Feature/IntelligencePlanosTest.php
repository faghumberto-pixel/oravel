<?php

namespace Tests\Feature;

use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Services\AssinaturaPlano;
use App\Domain\Intelligence\Trial\Services\CotaDiariaIa;
use App\Domain\Intelligence\Trial\Support\Planos;
use App\Livewire\PlanoNoPerfil;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class IntelligencePlanosTest extends TestCase
{
    use DatabaseTransactions;

    private function trial(array $over = []): IntelligenceTrial
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $t = Tenant::create(['name' => 'Locadora '.uniqid(), 'slug' => 'l-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $u = User::create(['name' => 'U', 'email' => 'u-'.uniqid().'@teste.com', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);

        return IntelligenceTrial::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $t->id, 'user_id' => $u->id, 'nome' => 'Maria', 'empresa' => $t->name, 'whatsapp' => '+5519'.random_int(900000000, 999999999),
            'status' => 'trial',
        ], $over));
    }

    private function todosProntos(): void
    {
        config(['intelligence_trial.modulos_prontos' => ['base', 'suprimentos', 'comercial', 'logistica']]);
    }

    public function test_degraus_sao_cumulativos_com_preco_e_cota_combinados(): void
    {
        $p = Planos::todos();
        $this->assertSame(['basico', 'standard', 'premium'], array_keys($p));
        $this->assertSame(['base'], $p['basico']['modulos']);
        $this->assertSame(['base', 'suprimentos'], $p['standard']['modulos']);
        $this->assertSame(['base', 'suprimentos', 'comercial', 'logistica'], $p['premium']['modulos']);
        $this->assertSame([99.0, 199.0], [$p['standard']['preco'], $p['premium']['preco']]);
        $this->assertSame([10, 25, 50], [$p['basico']['ia_por_dia'], $p['standard']['ia_por_dia'], $p['premium']['ia_por_dia']]);
    }

    public function test_modulo_que_nao_existe_ainda_impede_contratar_o_degrau(): void
    {
        $p = Planos::todos();
        $this->assertTrue($p['basico']['contratavel']);
        $this->assertFalse($p['standard']['contratavel']);
        $this->assertFalse($p['premium']['contratavel']);

        $this->todosProntos();
        $this->assertTrue(Planos::todos()['premium']['contratavel']);
    }

    public function test_basico_le_nome_e_preco_da_chave_plano(): void
    {
        config(['intelligence_trial.plano.preco' => 49.9]);
        $this->assertSame(49.9, Planos::achar('basico')['preco']);
    }

    public function test_cota_de_ia_segue_o_degrau_em_vigor(): void
    {
        $cota = app(CotaDiariaIa::class);
        $teste = $this->trial();
        $this->assertSame(1, $cota->limite($teste->tenant_id));

        $std = $this->trial(['contracted_at' => now(), 'plano_chave' => 'standard']);
        $this->assertSame(25, $cota->limite($std->tenant_id));

        $legado = $this->trial(['contracted_at' => now(), 'plano_chave' => null]); // contratou antes dos degraus: é o Básico
        $this->assertSame(10, $cota->limite($legado->tenant_id));
    }

    public function test_subir_de_plano_vale_na_hora_e_atualiza_o_valor_no_asaas(): void
    {
        $this->todosProntos();
        config(['services.asaas.api_key' => 'k', 'services.asaas.base_url' => 'https://asaas.test/v3']);
        Http::fake(['asaas.test/*' => Http::response(['id' => 'sub_1', 'nextDueDate' => now()->addDays(10)->toDateString()])]);
        $trial = $this->trial(['contracted_at' => now()->subMonth(), 'plano_chave' => 'basico', 'billing_starts_on' => now()->subDays(20)->toDateString(), 'asaas_subscription_id' => 'sub_1']);

        app(AssinaturaPlano::class)->trocarPlano($trial, 'standard');

        $trial->refresh();
        $this->assertSame('standard', Planos::efetivo($trial)['chave']);
        $this->assertSame(25, Planos::iaPorDia($trial));
        $this->assertNull($trial->plano_proximo_chave);
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r['value'] == 99.0 && $r['updatePendingPayments'] === false);
    }

    public function test_descer_de_plano_so_vale_na_proxima_cobranca(): void
    {
        $this->todosProntos();
        config(['services.asaas.api_key' => 'k', 'services.asaas.base_url' => 'https://asaas.test/v3']);
        $venc = now()->addDays(10)->toDateString();
        Http::fake(['asaas.test/*' => Http::response(['id' => 'sub_1', 'nextDueDate' => $venc])]);
        $trial = $this->trial(['contracted_at' => now()->subMonth(), 'plano_chave' => 'premium', 'billing_starts_on' => now()->subDays(20)->toDateString(), 'asaas_subscription_id' => 'sub_1']);

        app(AssinaturaPlano::class)->trocarPlano($trial, 'basico');

        $trial->refresh();
        $this->assertSame('premium', Planos::efetivo($trial)['chave'], 'continua no premium até o vencimento');
        $this->assertSame(50, Planos::iaPorDia($trial));
        $this->assertSame('basico', $trial->plano_proximo_chave);
        $this->assertSame($venc, $trial->plano_proximo_em->toDateString());

        $this->travelTo(now()->addDays(11));
        $this->assertSame('basico', Planos::efetivo($trial->fresh())['chave'], 'depois do vencimento vale o novo');
        $this->assertSame(10, Planos::iaPorDia($trial->fresh()));
    }

    public function test_troca_recusada_para_plano_nao_pronto_igual_ou_sem_contrato(): void
    {
        $servico = app(AssinaturaPlano::class);

        $contratado = $this->trial(['contracted_at' => now(), 'plano_chave' => 'basico', 'billing_starts_on' => now()->addDays(5)->toDateString()]);
        try {
            $servico->trocarPlano($contratado, 'standard'); // módulo Suprimentos ainda não existe
            $this->fail('deveria recusar');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('ainda não está disponível', $e->getMessage());
        }

        try {
            $servico->trocarPlano($contratado, 'basico');
            $this->fail('deveria recusar');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('já está neste plano', $e->getMessage());
        }

        $this->expectException(\DomainException::class);
        $servico->trocarPlano($this->trial(), 'basico'); // ainda em teste: precisa contratar
    }

    public function test_cartao_do_perfil_mostra_o_plano_e_so_aparece_para_cliente_do_intelligence(): void
    {
        $trial = $this->trial();
        $this->actingAs($trial->user);
        Livewire::test(PlanoNoPerfil::class)
            ->assertSee('Teste grátis')
            ->assertSee('Oravel Intelligence - Standard')
            ->assertSee('Em breve')
            ->assertSee('Perguntas ao assistente hoje')
            ->assertSee('0 de 1');
        $this->assertTrue(PlanoNoPerfil::canView());

        $this->todosProntos();
        $pago = $this->trial(['contracted_at' => now(), 'plano_chave' => 'standard', 'billing_starts_on' => now()->addDays(3)->toDateString()]);
        $this->actingAs($pago->user);
        Livewire::test(PlanoNoPerfil::class)
            ->assertSee('Oravel Intelligence - Standard')
            ->assertSee('Seu plano')
            ->assertSee('Subir para este')   // premium
            ->assertSee('Descer para este')  // basico
            ->assertSee('0 de 25');

        // usuário de empresa comum (sem conta do Intelligence) não vê o cartão
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $t = Tenant::create(['name' => 'Comum '.uniqid(), 'slug' => 'c-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $comum = User::create(['name' => 'C', 'email' => 'c-'.uniqid().'@teste.com', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $this->actingAs($comum);
        $this->assertFalse(PlanoNoPerfil::canView());
    }
}
