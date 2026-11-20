<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Relacionamento\Models\RelContato;
use App\Relacionamento\Models\RelInteracao;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Importador do CSV exportado do Sender. Dados FICTICIOS. DatabaseTransactions (ver Fase1Test). */
class RelacionamentoSenderCsvTest extends TestCase
{
    use DatabaseTransactions;

    private array $arquivos = [];

    protected function tearDown(): void
    {
        foreach ($this->arquivos as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function csv(string $conteudo): string
    {
        $f = tempnam(sys_get_temp_dir(), 'sender').'.csv';
        file_put_contents($f, $conteudo);

        return $this->arquivos[] = $f;
    }

    private function importar(string $arquivo, string $opcoes = '')
    {
        return $this->artisan('relacionamento:importar-sender '.escapeshellarg($arquivo).' '.$opcoes);
    }

    private const EN = "email,firstname,lastname,phone,tags,status,source,created,Empresa\n"
        ."ana.teste@exemplo.com,Ana,Teste,+55 11 98888-0001,Gazeta Watsapp,active,FORM,2026-11-10 09:30:00,Locadora Exemplo\n"
        ."bruno.teste@exemplo.com,Bruno,Lima,,\"Gazeta Watsapp, Outra\",active,IMPORT,2026-11-11 10:00:00,\n";

    public function test_importa_colunas_em_ingles_com_empresa_telefone_e_data(): void
    {
        $this->importar($this->csv(self::EN))->assertSuccessful();

        $ana = RelContato::where('email', 'ana.teste@exemplo.com')->firstOrFail();
        $this->assertSame('Ana Teste', $ana->nome);
        $this->assertSame('Locadora Exemplo', $ana->empresa_nome);
        $this->assertSame('11988880001', $ana->telefone_digitos);
        $this->assertSame('sender-form', $ana->consentimento_origem);

        $i = RelInteracao::where('contato_id', $ana->id)->firstOrFail();
        $this->assertSame('gazeta_inscricao', $i->tipo);
        $this->assertSame('2026-11-10 09:30:00', $i->ocorrido_em->format('Y-m-d H:i:s')); // data local, sem deslocar
    }

    public function test_so_formulario_registra_consentimento_a_menos_que_a_opcao_afirme(): void
    {
        $this->importar($this->csv(self::EN))->assertSuccessful();
        $this->assertNull(RelContato::where('email', 'bruno.teste@exemplo.com')->firstOrFail()->consentimento_em);

        $this->importar($this->csv(self::EN), '--consentimento-form')->assertSuccessful();
        $this->assertNotNull(RelContato::where('email', 'bruno.teste@exemplo.com')->firstOrFail()->fresh()->consentimento_em);
    }

    public function test_cabecalho_em_portugues_ponto_e_virgula_acentos_e_bom(): void
    {
        $arq = $this->csv("\xEF\xBB\xBF".mb_convert_encoding("E-mail;Nome;Sobrenome;Celular;Grupos;Situação;Data de inscrição\ncarla@exemplo.com;Carla;Mendes;(19) 97777-0002;Gazeta Oravel;Ativo;2026-11-12 08:00:00\n", 'ISO-8859-1', 'UTF-8'));

        $this->importar($arq)->assertSuccessful();

        $c = RelContato::where('email', 'carla@exemplo.com')->firstOrFail();
        $this->assertSame('Carla Mendes', $c->nome);
        $this->assertSame('19977770002', $c->telefone_digitos);
        $this->assertSame(1, RelInteracao::where('contato_id', $c->id)->count());
    }

    public function test_reimportar_nao_duplica_e_o_dry_run_nao_grava(): void
    {
        $arq = $this->csv(self::EN);

        $this->importar($arq, '--dry-run')->assertSuccessful();
        $this->assertSame(0, RelContato::count());

        $this->importar($arq)->assertSuccessful();
        $this->importar($arq)->assertSuccessful();
        $this->assertSame(2, RelContato::count());
        $this->assertSame(2, RelInteracao::count());
    }

    public function test_e_o_mesmo_dedupe_do_webhook_quem_entrou_pelos_dois_caminhos_conta_uma_vez(): void
    {
        config(['services.relacionamento.sender_secret' => 'seg']);
        $corpo = json_encode(['id' => 'X1', 'email' => 'ana.teste@exemplo.com', 'source' => 'FORM', 'subscriber_tags' => [['id' => 't', 'title' => 'Gazeta Watsapp']], 'created_utc' => '2026-11-10 12:00:00']);
        $this->call('POST', '/api/relacionamento/sender/subscribers-new', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SENDER_HMAC_SHA256' => hash_hmac('sha256', $corpo, 'seg')], $corpo)->assertOk();

        $this->importar($this->csv(self::EN))->assertSuccessful();

        $this->assertSame(1, RelInteracao::whereHas('contato', fn ($q) => $q->where('email', 'ana.teste@exemplo.com'))->count());
    }

    public function test_inativo_desconhecido_nao_e_criado_e_conhecido_e_marcado_descadastrado(): void
    {
        $conhecido = RelContato::create(['email' => 'sai@exemplo.com']);
        $arq = $this->csv("email,tags,status\nsai@exemplo.com,Gazeta Watsapp,unsubscribed\nnunca@exemplo.com,Gazeta Watsapp,unsubscribed\nbounce@exemplo.com,Gazeta Watsapp,bounced\n");

        $this->importar($arq)->assertSuccessful();

        $this->assertNotNull($conhecido->fresh()->descadastrado_em);
        $this->assertNull(RelContato::where('email', 'nunca@exemplo.com')->first());
        $this->assertNull(RelContato::where('email', 'bounce@exemplo.com')->first());
        $this->assertSame(0, RelInteracao::count());
    }

    public function test_coluna_de_data_de_descadastro_preenchida_conta_como_inativo(): void
    {
        $c = RelContato::create(['email' => 'saiu@exemplo.com']);
        $this->importar($this->csv("email,tags,status,unsubscribed_at\nsaiu@exemplo.com,Gazeta Watsapp,active,2026-11-15 10:00:00\n"))->assertSuccessful();

        $this->assertSame('2026-11-15', $c->fresh()->descadastrado_em->format('Y-m-d'));
        $this->assertSame(0, RelInteracao::count());
    }

    public function test_quem_voltou_a_ficar_ativo_perde_a_marca_de_descadastro(): void
    {
        $c = RelContato::create(['email' => 'volta@exemplo.com', 'descadastrado_em' => now()->subMonth()]);

        $this->importar($this->csv("email,tags,status\nvolta@exemplo.com,Gazeta Watsapp,active\n"))->assertSuccessful();

        $this->assertNull($c->fresh()->descadastrado_em);
    }

    public function test_tag_sem_regra_e_ignorada_e_relatada_e_o_tipo_padrao_resolve(): void
    {
        $arq = $this->csv("email,tags,status\nx@exemplo.com,Lista Misteriosa,active\n");

        $this->importar($arq)->expectsOutputToContain('Lista Misteriosa')->assertSuccessful();
        $this->assertSame(0, RelInteracao::count());

        $this->importar($arq, '--tipo-padrao=gazeta_inscricao')->assertSuccessful();
        $this->assertSame(1, RelInteracao::where('tipo', 'gazeta_inscricao')->count());
    }

    public function test_tipo_padrao_invalido_e_arquivo_inexistente_falham(): void
    {
        $this->importar($this->csv("email,status\na@b.com,active\n"), '--tipo-padrao=inventado')->assertFailed();
        $this->importar('/tmp/nao-existe-'.uniqid().'.csv')->assertFailed();
    }

    public function test_sem_coluna_de_email_falha_mostrando_as_colunas_e_o_mapa_corrige(): void
    {
        $arq = $this->csv("Contato principal,tags,status\nmapa@exemplo.com,Gazeta Watsapp,active\n");

        $this->importar($arq)->expectsOutputToContain('Contato principal')->assertFailed();

        $this->importar($arq, '--mapa="Contato principal=email"')->assertSuccessful();
        $this->assertNotNull(RelContato::where('email', 'mapa@exemplo.com')->first());
    }

    public function test_email_invalido_e_linha_vazia_sao_ignorados_e_liga_a_empresa_pelo_email(): void
    {
        $p = Prospect::create(['cnpj' => '11222333000144', 'nome' => 'Locadora Z', 'email' => 'dono@z.com']);
        $arq = $this->csv("email,tags,status\nnao-e-email,Gazeta Watsapp,active\n\n,,\ndono@z.com,Gazeta Watsapp,active\n");

        $this->importar($arq)->assertSuccessful();

        $this->assertSame(1, RelContato::count());
        $this->assertSame($p->id, RelInteracao::firstOrFail()->prospect_id);
    }
}
