<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\CrmProposta;
use App\Models\DocumentoModelo;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ModeloDeDocumento;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** O modelo de documento da empresa é aplicado ao imprimir orçamento, contrato e proposta, sempre o da empresa dona do documento. */
class DocumentoModeloImpressaoTest extends TestCase
{
    use DatabaseTransactions;

    private function empresa(string $nome = 'Locadora'): Tenant
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_documento_modelos' => true]]);

        return Tenant::create(['name' => $nome.' '.uniqid(), 'slug' => 'di-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function modelo(Tenant $t, string $tipo, array $extra = []): DocumentoModelo
    {
        return DocumentoModelo::withoutGlobalScopes()->create($extra + [
            'tenant_id' => $t->id, 'tipo' => $tipo, 'nome' => 'Modelo '.uniqid(), 'is_default' => true,
            'cabecalho' => "Razão Social do Modelo Ltda\nCNPJ 11.111.111/0001-11",
            'campos' => [['titulo' => 'CLÁUSULA 1 – OBJETO', 'texto' => 'Locação para {{cliente}}, CNPJ {{cnpj}}, no valor de {{valor_total}}.'], ['titulo' => 'Garantia', 'texto' => 'Sem garantia de {{validade}}.']],
            'termos' => 'Termos do modelo para {{empresa}}.', 'rodape' => 'Foro de Campinas/SP',
        ]);
    }

    private function orcamento(Tenant $t): Quote
    {
        $c = Client::create(['tenant_id' => $t->id, 'name' => 'Construtora Alfa', 'cpf_cnpj' => '22.222.222/0001-22']);

        return Quote::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'status' => Quote::STATUS_RASCUNHO, 'total_value' => 1500])->load(['client', 'items']);
    }

    private function contrato(Tenant $t): Contract
    {
        $c = Client::create(['tenant_id' => $t->id, 'name' => 'Construtora Beta', 'cpf_cnpj' => '33.333.333/0001-33']);
        $a = Asset::create(['tenant_id' => $t->id, 'name' => 'Gerador', 'status' => 'disponivel']);

        return Contract::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'asset_id' => $a->id, 'contract_number' => 'CT-77', 'start_date' => now(), 'end_date' => now()->addMonths(6), 'billing_type' => 'fixo', 'price' => 8000])->load(['client', 'asset']);
    }

    public function test_orcamento_impresso_usa_o_modelo_com_marcadores_preenchidos(): void
    {
        $t = $this->empresa();
        $this->modelo($t, 'orcamento');
        $q = $this->orcamento($t);

        $html = view('pdf.quote', ['quote' => $q, 'generatedAt' => now()->format('d/m/Y H:i')])->render();

        $this->assertStringContainsString('Razão Social do Modelo Ltda', $html, 'cabeçalho do modelo no lugar da marca padrão');
        $this->assertStringContainsString('CLÁUSULA 1 – OBJETO', $html);
        $this->assertStringContainsString('Locação para Construtora Alfa, CNPJ 22.222.222/0001-22, no valor de R$ 1.500,00.', $html, 'marcadores trocados pelos dados do orçamento');
        $this->assertStringContainsString('Sem garantia de —.', $html, 'marcador sem valor vira traço, nunca a chave crua');
        $this->assertStringNotContainsString('{{', $html);
        $this->assertStringContainsString('Foro de Campinas/SP', $html, 'rodapé do modelo');
    }

    public function test_sem_modelo_o_documento_continua_como_antes(): void
    {
        $t = $this->empresa();
        $q = $this->orcamento($t);

        $html = view('pdf.quote', ['quote' => $q, 'generatedAt' => now()->format('d/m/Y H:i')])->render();

        $this->assertStringContainsString('class="logo-text"', $html, 'marca padrão');
        $this->assertStringNotContainsString('CLÁUSULA 1', $html);
    }

    public function test_modelo_inativo_ou_de_outro_tipo_nao_vale(): void
    {
        $t = $this->empresa();
        $this->modelo($t, 'orcamento', ['is_active' => false]);
        $this->modelo($t, 'contrato');
        $q = $this->orcamento($t);

        $this->assertNull(ModeloDeDocumento::doOrcamento($q));
        $this->assertNotNull(ModeloDeDocumento::doContrato($this->contrato($t)));
    }

    public function test_sem_usuario_logado_so_vale_o_modelo_da_empresa_dona_do_documento(): void
    {
        $a = $this->empresa('A');
        $b = $this->empresa('B');
        $this->modelo($b, 'orcamento', ['cabecalho' => 'MODELO DA EMPRESA B']);
        $qa = $this->orcamento($a);
        $this->assertGuest();

        // Fila/assinatura/console: sem usuário o escopo por empresa não filtra. O documento da A nunca pode usar o modelo da B.
        $this->assertNull(ModeloDeDocumento::doOrcamento($qa));
        $html = view('pdf.quote', ['quote' => $qa, 'generatedAt' => 'x'])->render();
        $this->assertStringNotContainsString('MODELO DA EMPRESA B', $html);
    }

    public function test_contrato_impresso_traz_as_clausulas_do_cliente(): void
    {
        $t = $this->empresa();
        $this->modelo($t, 'contrato');
        $c = $this->contrato($t);

        $html = view('pdf.contract', ['contract' => $c, 'generatedAt' => 'x'])->render();

        $this->assertStringContainsString('Razão Social do Modelo Ltda', $html);
        $this->assertStringContainsString('Locação para Construtora Beta, CNPJ 33.333.333/0001-33, no valor de R$ 8.000,00.', $html);
        $this->assertStringContainsString('Sem garantia de '.now()->addMonths(6)->format('d/m/Y').'.', $html, 'marcador {{validade}} = fim do contrato');
    }

    public function test_proposta_do_crm_usa_o_modelo(): void
    {
        $t = $this->empresa();
        $this->modelo($t, 'proposta');
        $v = User::create(['name' => 'Vendedor', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'is_approved' => true]);
        $p = CrmProposta::create(['tenant_id' => $t->id, 'vendedor_user_id' => $v->id, 'titulo' => 'Locação de geradores', 'validade_ate' => now()->addDays(10)]);
        $p->itens()->create(['tenant_id' => $t->id, 'descricao' => 'Gerador 100kVA', 'quantidade' => 2, 'preco_unitario' => 1500]);

        $html = view('pdf.crm-proposta', ['proposta' => $p->refresh()->load(['itens', 'conta', 'lead', 'vendedor', 'tenant'])])->render();

        $this->assertStringContainsString('Razão Social do Modelo Ltda', $html);
        $this->assertStringContainsString('CLÁUSULA 1 – OBJETO', $html);
        $this->assertStringContainsString('no valor de R$ 3.000,00.', $html);
        $this->assertStringContainsString('Foro de Campinas/SP', $html);
        $this->assertStringStartsWith('%PDF', $p->gerarPdf(), 'o PDF continua sendo gerado com o modelo');
    }

    public function test_marcador_empresa_e_o_nome_da_empresa_dona_do_documento_e_nao_o_emissor_do_sistema(): void
    {
        $t = $this->empresa('Locadora Marcador');
        $this->modelo($t, 'contrato');
        $this->modelo($t, 'orcamento');
        $this->modelo($t, 'proposta');

        $contrato = ModeloDeDocumento::doContrato($this->contrato($t));
        $orcamento = ModeloDeDocumento::doOrcamento($this->orcamento($t));

        $this->assertSame('Termos do modelo para '.$t->name.'.', $contrato['termos']);
        $this->assertSame('Termos do modelo para '.$t->name.'.', $orcamento['termos']);
        $this->assertStringNotContainsString('Oravel', (string) $contrato['termos']);
    }
}
