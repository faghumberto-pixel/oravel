<?php

namespace Tests\Unit\ProspectImport;

use App\Services\ProspectImport\ProspectImportService as S;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Puro (sem app/banco): vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Unit/ProspectImport */
class ProspectImportServiceTest extends TestCase
{
    private const CNPJ = '43.368.422/0001-27';

    private const CNPJ_VALIDO_2 = '11.222.333/0001-81';

    public function test_cnpj_valido_com_e_sem_pontuacao_vira_formatado(): void
    {
        foreach ([self::CNPJ, '43368422000127', ' 43.368.422/0001-27 '] as $entrada) {
            $r = S::converter(['cnpj' => $entrada, 'nome' => 'X']);
            $this->assertNull($r['erro'], $entrada);
            $this->assertSame(self::CNPJ, $r['dados']['cnpj']);
        }
    }

    public function test_cnpj_invalido_ou_vazio_e_erro(): void
    {
        $this->assertSame('CNPJ em branco', S::converter(['cnpj' => '', 'nome' => 'X'])['erro']);
        $this->assertStringStartsWith('CNPJ inválido', S::converter(['cnpj' => '12.345.678/0001-00', 'nome' => 'X'])['erro']);
        $this->assertStringStartsWith('CNPJ inválido', S::converter(['cnpj' => '11111111111111', 'nome' => 'X'])['erro']);
        $this->assertStringStartsWith('CNPJ inválido', S::converter(['cnpj' => '123', 'nome' => 'X'])['erro']);
    }

    public function test_excel_que_perdeu_zero_a_esquerda_e_restaurado(): void
    {
        // CNPJ 00.360.305/0001-04 digitado como numero no Excel vira 360305000104 (12 digitos)
        $r = S::converter(['cnpj' => '360305000104', 'nome' => 'Banco']);

        $this->assertNull($r['erro']);
        $this->assertSame('00.360.305/0001-04', $r['dados']['cnpj']);
        $this->assertContains('zeros à esquerda do CNPJ restaurados', $r['avisos']);
    }

    public function test_razao_social_vira_nome_so_como_derivado_para_empresa_nova(): void
    {
        $r = S::converter(['cnpj' => self::CNPJ, 'razao_social' => 'Exemplo Ltda']);

        $this->assertNull($r['erro']);
        $this->assertArrayNotHasKey('nome', $r['dados']);          // empresa que ja existe nao recebe nome deduzido
        $this->assertSame('Exemplo Ltda', $r['derivados']['nome']); // empresa nova usa a razao social
    }

    public function test_linha_so_com_cnpj_e_valida_para_atualizar_quem_ja_existe(): void
    {
        $r = S::converter(['cnpj' => self::CNPJ, 'telefone' => '1930000000']);

        $this->assertNull($r['erro']);
        $this->assertSame('(19) 3000-0000', $r['dados']['telefone']);
        $this->assertArrayNotHasKey('nome', $r['derivados']);
    }

    #[DataProvider('datas')]
    public function test_data_de_abertura(string $entrada, ?string $esperada): void
    {
        $this->assertSame($esperada, S::data($entrada));
    }

    public static function datas(): array
    {
        return [
            'iso' => ['2018-03-15', '2018-03-15'],
            'iso com hora' => ['2018-03-15 10:20:00', '2018-03-15'],
            'br barra' => ['15/03/2018', '2018-03-15'],
            'br traco' => ['15-03-2018', '2018-03-15'],
            'br ponto' => ['15.03.2018', '2018-03-15'],
            'ano primeiro com barra' => ['2018/03/15', '2018-03-15'],
            'dia inexistente' => ['31/02/2018', null],
            'ano fora da faixa' => ['15/03/1700', null],
            'texto' => ['ontem', null],
            'vazio' => ['', null],
        ];
    }

    public function test_data_completa_define_o_ano_e_a_empresa_nova(): void
    {
        $velha = S::converter(['cnpj' => self::CNPJ, 'nome' => 'X', 'data_abertura' => '15/03/2018', 'ano_abertura' => '1999']);
        $this->assertSame('2018-03-15', $velha['dados']['data_abertura']);
        $this->assertSame(2018, $velha['dados']['ano_abertura']); // a data completa manda sobre o ano solto
        $this->assertFalse($velha['derivados']['empresa_nova']);

        $nova = S::converter(['cnpj' => self::CNPJ, 'nome' => 'X', 'data_abertura' => '02/05/2024']);
        $this->assertTrue($nova['derivados']['empresa_nova']);
    }

    public function test_so_o_ano_tambem_funciona_e_ano_absurdo_vira_aviso(): void
    {
        $r = S::converter(['cnpj' => self::CNPJ, 'nome' => 'X', 'ano_abertura' => '2015']);
        $this->assertSame(2015, $r['dados']['ano_abertura']);
        $this->assertArrayNotHasKey('data_abertura', $r['dados']);

        $ruim = S::converter(['cnpj' => self::CNPJ, 'nome' => 'X', 'ano_abertura' => '3000']);
        $this->assertArrayNotHasKey('ano_abertura', $ruim['dados']);
        $this->assertNotEmpty($ruim['avisos']);
    }

    public function test_empresa_nova_informada_na_planilha_nao_e_derivada(): void
    {
        $r = S::converter(['cnpj' => self::CNPJ, 'nome' => 'X', 'ano_abertura' => '2025', 'empresa_nova' => 'não']);

        $this->assertFalse($r['dados']['empresa_nova']);
        $this->assertArrayNotHasKey('empresa_nova', $r['derivados']);
    }

    #[DataProvider('cnaes')]
    public function test_codigo_do_cnae(string $entrada, string $esperado, bool $comAviso): void
    {
        $r = S::converter(['cnpj' => self::CNPJ, 'nome' => 'X', 'cnae_codigo' => $entrada]);

        $this->assertSame($esperado, $r['dados']['cnae_codigo']);
        $this->assertSame($comAviso, $r['avisos'] !== []);
    }

    public static function cnaes(): array
    {
        return [
            'so digitos' => ['7739099', '7739-0/99', false],
            'ja formatado' => ['7739-0/99', '7739-0/99', false],
            'com ponto' => ['7739.0/99', '7739-0/99', false],
            'tamanho estranho' => ['77390', '77390', true],
        ];
    }

    #[DataProvider('capitais')]
    public function test_capital(string $entrada, ?int $esperado): void
    {
        $this->assertSame($esperado, S::numero($entrada));
    }

    public static function capitais(): array
    {
        return [
            'inteiro' => ['480000000', 480000000],
            'pt-BR com milhar' => ['1.234.567,89', 1234568],
            'pt-BR com R$' => ['R$ 50.000,00', 50000],
            'virgula decimal' => ['1500,50', 1501],
            'ponto decimal' => ['1500.50', 1501],
            'milhar sem decimal' => ['2.500.000', 2500000],
            'negativo' => ['-10', null],
            'texto' => ['muito', null],
        ];
    }

    #[DataProvider('portes')]
    public function test_porte(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, S::porte($entrada));
    }

    public static function portes(): array
    {
        return [
            ['EPP', 'EPP'], ['me', 'ME'], ['DEMAIS', 'DEMAIS'], ['MEI', 'MEI'],
            ['01', 'ME'], ['03', 'EPP'], ['05', 'DEMAIS'],
            ['Empresa de Pequeno Porte', 'EPP'], ['Micro Empresa', 'ME'], ['Microempreendedor Individual', 'MEI'],
            ['Médio', 'DEMAIS'], ['Grande Porte', 'DEMAIS'],
        ];
    }

    #[DataProvider('telefones')]
    public function test_telefone(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, S::telefone($entrada));
    }

    public static function telefones(): array
    {
        return [
            'fixo so digitos' => ['1935450500', '(19) 3545-0500'],
            'celular' => ['19999332615', '(19) 99933-2615'],
            'com 55' => ['+55 19 99933-2615', '(19) 99933-2615'],
            'ja formatado' => ['(11) 3545-0500', '(11) 3545-0500'],
            'curto fica como veio' => ['3545-0500', '3545-0500'],
        ];
    }

    public function test_email_invalido_e_ignorado_com_aviso_e_valido_vai_em_minuscula(): void
    {
        $bom = S::converter(['cnpj' => self::CNPJ, 'nome' => 'X', 'email' => 'Contato@Exemplo.COM.br']);
        $this->assertSame('contato@exemplo.com.br', $bom['dados']['email']);

        $ruim = S::converter(['cnpj' => self::CNPJ, 'nome' => 'X', 'email' => 'nao-e-email']);
        $this->assertArrayNotHasKey('email', $ruim['dados']);
        $this->assertCount(1, $ruim['avisos']);
    }

    public function test_celula_em_branco_nao_gera_campo(): void
    {
        $r = S::converter(['cnpj' => self::CNPJ, 'nome' => 'X', 'cidade' => '', 'telefone' => '  ', 'email' => '', 'cnae_codigo' => '', 'capital' => '']);

        $this->assertSame(['cnpj', 'nome'], array_keys($r['dados']));
    }

    public function test_nota_e_limitada_de_zero_a_dez(): void
    {
        $this->assertSame(10, S::converter(['cnpj' => self::CNPJ, 'nome' => 'X', 'nota' => '15'])['dados']['nota']);
        $this->assertSame(0, S::converter(['cnpj' => self::CNPJ, 'nome' => 'X', 'nota' => '-3'])['dados']['nota']);
        $this->assertSame(8, S::converter(['cnpj' => self::CNPJ, 'nome' => 'X', 'nota' => '7,6'])['dados']['nota']);
    }

    public function test_textos_longos_sao_cortados_no_tamanho_do_cadastro(): void
    {
        $r = S::converter(['cnpj' => self::CNPJ, 'nome' => str_repeat('a', 500), 'atividade' => str_repeat('b', 500)]);

        $this->assertSame(200, mb_strlen($r['dados']['nome']));
        $this->assertSame(160, mb_strlen($r['dados']['atividade']));
    }

    public function test_cabecalhos_do_apify_da_receita_e_da_lista_compacta_sao_reconhecidos(): void
    {
        $apify = ['CNPJ', 'Nome Fantasia', 'Razão Social', 'Município', 'Porte da Empresa', 'Capital Social', 'Data Início Atividade', 'CNAE Fiscal', 'CNAE Fiscal Descrição', 'Telefone 1', 'E-mail', 'Sócio', 'Qualificação do Sócio'];
        $m = S::sugerirMapeamento($apify);

        $this->assertSame(0, $m['cnpj']);
        $this->assertSame(1, $m['nome']);
        $this->assertSame(2, $m['razao_social']);
        $this->assertSame(3, $m['cidade']);
        $this->assertSame(4, $m['porte']);
        $this->assertSame(5, $m['capital']);
        $this->assertSame(6, $m['data_abertura']);
        $this->assertSame(7, $m['cnae_codigo']);
        $this->assertSame(8, $m['atividade']);
        $this->assertSame(9, $m['telefone']);
        $this->assertSame(10, $m['email']);
        $this->assertSame(11, $m['socio']);
        $this->assertSame(12, $m['socio_cargo']);

        // formato compacto da "Lista Oravel SP"
        $compacto = S::sugerirMapeamento(['n', 'l', 'c', 's', 'k', 'a', 't', 'tel', 'em', 'p', 'pr', 'cnpj', 'sc', 'rg', 'nv']);
        $this->assertEquals(['nome' => 0, 'razao_social' => 1, 'cidade' => 2, 'porte' => 3, 'capital' => 4, 'ano_abertura' => 5, 'atividade' => 6, 'telefone' => 7, 'email' => 8, 'socio' => 9, 'socio_cargo' => 10, 'cnpj' => 11, 'nota' => 12, 'regiao_campinas' => 13, 'empresa_nova' => 14],
            array_filter($compacto, fn ($v) => $v !== null));
    }

    public function test_coluna_nao_e_usada_por_dois_campos(): void
    {
        $m = S::sugerirMapeamento(['CNPJ', 'Atividade']);

        $this->assertSame(1, $m['atividade']);
        $this->assertNull($m['cnae_codigo']);
    }

    public function test_todo_campo_tem_rotulo_e_so_o_cnpj_e_obrigatorio(): void
    {
        $obrigatorios = array_keys(array_filter(S::CAMPOS, fn ($d) => $d['obrigatorio']));

        $this->assertSame(['cnpj'], $obrigatorios);
        foreach (S::CAMPOS as $campo => $def) {
            $this->assertNotSame('', $def['rotulo'], $campo);
        }
    }

    public function test_modelo_csv_tem_cabecalhos_que_o_proprio_importador_reconhece(): void
    {
        $linhas = array_map('str_getcsv', explode("\n", trim(ltrim(S::modeloCsv(), "\xEF\xBB\xBF"))), array_fill(0, 2, ';'));
        $m = S::sugerirMapeamento($linhas[0]);

        foreach (['cnpj', 'nome', 'razao_social', 'cidade', 'porte', 'capital', 'data_abertura', 'cnae_codigo', 'atividade', 'telefone', 'email', 'socio', 'socio_cargo'] as $campo) {
            $this->assertNotNull($m[$campo], "o modelo nao liga o campo {$campo}");
        }

        $r = S::converter(array_combine(array_keys(array_filter($m, fn ($v) => $v !== null)), array_map(fn ($i) => $linhas[1][$i], array_filter($m, fn ($v) => $v !== null))));
        $this->assertNull($r['erro'], 'a linha de exemplo do modelo precisa ser importavel');
    }
}
