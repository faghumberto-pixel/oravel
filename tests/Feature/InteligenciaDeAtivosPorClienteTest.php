<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ContratoResource;
use App\Filament\Central\Resources\TenantResource\Pages\ListTenants;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustoManutencaoService;
use App\Services\InteligenciaDeAtivosService;
use App\Services\LocalizacaoEquipamentosService;
use App\Services\ProdutosDaEmpresa;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Ligar a Inteligencia de Ativos por cliente, pela Central, sem afetar os outros clientes do mesmo contrato. */
class InteligenciaDeAtivosPorClienteTest extends TestCase
{
    use DatabaseTransactions;

    private function vitrine(string $codigo, float $preco): Plan
    {
        $p = Pacotes::planoDeVitrine($codigo);
        $p->update(['base_price' => $preco, 'price' => $preco, 'is_active' => true, 'implementation_fee' => 0]);

        return $p->fresh();
    }

    private function empresa(Plan $plano): Tenant
    {
        return Tenant::factory()->create(['plan_id' => $plano->id, 'payment_method' => 'cartao', 'asaas_subscription_id' => 'sub_x', 'mrr_value' => 100]);
    }

    public function test_liga_so_para_um_cliente_copiando_o_contrato_compartilhado(): void
    {
        $vitrine = $this->vitrine('locadora', 900);
        $a = $this->empresa($vitrine);
        $b = $this->empresa($vitrine);
        $servico = app(InteligenciaDeAtivosService::class);

        $this->assertSame('Desligado', $servico->resumo($a));

        $a = $servico->definir($a, [CustoManutencaoService::FEATURE, LocalizacaoEquipamentosService::FEATURE]);

        $this->assertNotSame($vitrine->id, $a->plan_id, 'o cliente ganhou contrato exclusivo');
        $this->assertSame('Custo + Localização', $servico->resumo($a));
        $this->assertTrue($a->plan->hasFeature('tabela_assets'), 'o resto do contrato foi copiado');
        // O outro cliente e a vitrine seguem sem as telas.
        $this->assertSame($vitrine->id, $b->fresh()->plan_id);
        $this->assertSame('Desligado', $servico->resumo($b->fresh()));
        $this->assertFalse($vitrine->fresh()->hasFeature(CustoManutencaoService::FEATURE));

        // So' custo; depois desliga tudo; ligar de novo com o mesmo estado nao cria outra copia.
        $a = $servico->definir($a, [CustoManutencaoService::FEATURE]);
        $this->assertSame('Só custo', $servico->resumo($a));
        $contrato = $a->plan_id;
        $a = $servico->definir($a, []);
        $this->assertSame('Desligado', $servico->resumo($a));
        $this->assertSame($contrato, $servico->definir($a, [])->plan_id);
        $this->assertSame($contrato, $a->plan_id);
    }

    public function test_funciona_com_contrato_em_lista_ou_em_mapa_de_chaves(): void
    {
        $servico = app(InteligenciaDeAtivosService::class);

        $lista = Plan::create(['name' => 'Lista '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets']]);
        $mapa = Plan::create(['name' => 'Mapa '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets' => true]]);

        foreach ([$lista, $mapa] as $plano) {
            $t = Tenant::factory()->create(['plan_id' => $plano->id]);
            $t = $servico->definir($t, [CustoManutencaoService::FEATURE]);
            $this->assertSame($plano->id, $t->plan_id, 'contrato exclusivo do cliente nao e copiado');
            $this->assertTrue($t->plan->hasFeature(CustoManutencaoService::FEATURE));
            $this->assertFalse($t->plan->hasFeature(LocalizacaoEquipamentosService::FEATURE));
            $this->assertTrue($t->plan->hasFeature('tabela_assets'));
            $t = $servico->definir($t, []);
            $this->assertFalse($t->plan->fresh()->hasFeature(CustoManutencaoService::FEATURE));
        }
    }

    public function test_trocar_produtos_na_central_nao_perde_a_inteligencia_de_ativos(): void
    {
        config(['services.asaas.api_key' => 'KEY', 'services.asaas.base_url' => 'https://asaas.test/v3']);
        Http::fake(['asaas.test/*' => Http::response(['id' => 'sub_x'], 200)]);

        $servicos = $this->vitrine('servicos', 300);
        $this->vitrine('vendas', 200);
        $empresa = app(InteligenciaDeAtivosService::class)->definir($this->empresa($servicos), [LocalizacaoEquipamentosService::FEATURE]);
        $this->assertSame('Só localização', app(InteligenciaDeAtivosService::class)->resumo($empresa));

        $depois = app(ProdutosDaEmpresa::class)->alterar($empresa, ['servicos', 'vendas']);

        $this->assertEqualsCanonicalizing(['servicos', 'vendas'], Pacotes::produtosDoTenant($depois));
        $this->assertSame('Só localização', app(InteligenciaDeAtivosService::class)->resumo($depois), 'a chave sobreviveu a troca de contrato');
        $this->assertFalse(Pacotes::vitrine('vendas')->hasFeature(LocalizacaoEquipamentosService::FEATURE), 'a vitrine segue limpa');
    }

    public function test_central_liga_pela_lista_de_empresas_e_a_coluna_mostra(): void
    {
        config(['oravel.super_admins' => ['equipe@oravel.test']]);
        Filament::setCurrentPanel(Filament::getPanel('central'));
        $u = User::factory()->create(['email' => 'equipe@oravel.test']);
        $vitrine = $this->vitrine('locadora', 900);
        $t = $this->empresa($vitrine);

        Livewire::actingAs($u)->test(ListTenants::class)
            ->assertTableActionExists('inteligenciaDeAtivos')
            ->callTableAction('inteligenciaDeAtivos', $t, data: ['chaves' => [CustoManutencaoService::FEATURE, LocalizacaoEquipamentosService::FEATURE]])
            ->assertHasNoTableActionErrors()
            ->assertTableColumnStateSet('inteligencia_ativos', 'Custo + Localização', record: $t->fresh());

        $this->assertTrue($t->fresh()->hasFeature(CustoManutencaoService::FEATURE));
        $this->assertFalse($vitrine->fresh()->hasFeature(CustoManutencaoService::FEATURE));
    }

    public function test_modulos_aparecem_na_tela_de_contratos_da_central_e_podem_ser_marcados(): void
    {
        $opcoes = ContratoResource::groupedFeatureOptions();

        $this->assertArrayHasKey('Inteligência de Ativos', $opcoes);
        $this->assertSame([CustoManutencaoService::FEATURE, LocalizacaoEquipamentosService::FEATURE], array_keys($opcoes['Inteligência de Ativos']));
    }
}
