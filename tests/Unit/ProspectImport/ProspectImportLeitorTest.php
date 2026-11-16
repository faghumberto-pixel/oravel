<?php

namespace Tests\Unit\ProspectImport;

use App\Services\ProspectImport\ProspectImportLeitor;
use PHPUnit\Framework\TestCase;

/** Le arquivos de verdade (CSV com BOM/; , e JSON), sem banco. */
class ProspectImportLeitorTest extends TestCase
{
    private array $temporarios = [];

    protected function tearDown(): void
    {
        foreach ($this->temporarios as $f) {
            @unlink($f);
        }
    }

    private function arquivo(string $conteudo, string $ext): string
    {
        $f = tempnam(sys_get_temp_dir(), 'pi').'.'.$ext;
        file_put_contents($f, $conteudo);
        $this->temporarios[] = $f;

        return $f;
    }

    public function test_json_lista_de_objetos(): void
    {
        $f = $this->arquivo(json_encode([
            ['n' => 'A', 'cnpj' => '43.368.422/0001-27', 'k' => 480000000, 'nv' => false],
            ['n' => 'B', 'cnpj' => '11.222.333/0001-81', 'tel' => '(11) 1234-5678', 'nv' => true],
        ]), 'json');

        $r = (new ProspectImportLeitor)->ler($f);

        $this->assertSame(['n', 'cnpj', 'k', 'nv', 'tel'], $r['cabecalhos']); // uniao das chaves, na ordem em que aparecem
        $this->assertSame([1, 2], array_keys($r['linhas']));
        $this->assertSame(['A', '43.368.422/0001-27', '480000000', 'não', ''], $r['linhas'][1]);
        $this->assertSame('sim', $r['linhas'][2][3]);
        $this->assertSame('(11) 1234-5678', $r['linhas'][2][4]);
    }

    public function test_json_embrulhado_em_data_ou_items(): void
    {
        foreach (['data', 'items', 'prospects', 'empresas'] as $chave) {
            $r = (new ProspectImportLeitor)->ler($this->arquivo(json_encode([$chave => [['cnpj' => '1', 'nome' => 'A']]]), 'json'));
            $this->assertSame(['cnpj', 'nome'], $r['cabecalhos'], $chave);
        }
    }

    public function test_json_invalido_ou_que_nao_e_lista_de_empresas_da_erro_claro(): void
    {
        foreach (['nao e json', '{"a": 1}', '[]', '[1,2,3]', '"texto"'] as $ruim) {
            try {
                (new ProspectImportLeitor)->ler($this->arquivo($ruim, 'json'));
                $this->fail("deveria recusar: {$ruim}");
            } catch (\DomainException $e) {
                $this->assertStringContainsString('lista de empresas', $e->getMessage());
            }
        }
    }

    public function test_json_com_mais_linhas_que_o_limite_e_recusado(): void
    {
        $muitos = array_fill(0, ProspectImportLeitor::MAX_LINHAS + 1, ['cnpj' => '1']);

        $this->expectException(\DomainException::class);
        (new ProspectImportLeitor)->ler($this->arquivo(json_encode($muitos), 'json'));
    }

    public function test_csv_com_ponto_e_virgula_bom_e_acentos(): void
    {
        $csv = "\xEF\xBB\xBFCNPJ;Razão Social;Cidade\n43.368.422/0001-27;Locações São José Ltda;Jacareí\n";

        $r = (new ProspectImportLeitor)->ler($this->arquivo($csv, 'csv'));

        $this->assertSame(['CNPJ', 'Razão Social', 'Cidade'], $r['cabecalhos']);
        $this->assertSame(['43.368.422/0001-27', 'Locações São José Ltda', 'Jacareí'], array_values($r['linhas'])[0]);
    }

    public function test_csv_windows_1252_e_virgula(): void
    {
        $csv = mb_convert_encoding("cnpj,nome\n43.368.422/0001-27,Locações Ação\n", 'Windows-1252', 'UTF-8');

        $r = (new ProspectImportLeitor)->ler($this->arquivo($csv, 'csv'));

        $this->assertSame('Locações Ação', array_values($r['linhas'])[0][1]);
    }

    public function test_arquivo_inexistente_e_vazio_dao_erro(): void
    {
        $this->expectException(\DomainException::class);
        (new ProspectImportLeitor)->ler('/tmp/nao-existe-'.uniqid().'.json');
    }
}
