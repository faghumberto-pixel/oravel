<?php

namespace Tests\Feature;

use App\Models\PropostaComercial;
use App\Models\PropostaComercialTemplate;
use App\Services\AnthropicApiClient;
use App\Services\PropostaTemplateImportador;
use Tests\TestCase;

class PropostaTemplateImportadorTest extends TestCase
{
    public function test_normaliza_resposta_da_ia_em_campos_do_template(): void
    {
        $resposta = json_encode([
            'nome' => 'Proposta de locação',
            'cabecalho' => "Locadora X\nCNPJ 00.000.000/0001-00",
            'termos' => 'Condições gerais.',
            'validade_dias' => '15',
            'campos' => [
                ['titulo' => 'Condições de pagamento', 'texto' => '30 dias'],
                ['titulo' => '  ', 'texto' => 'sem título some'],
                ['titulo' => 'Garantia'],
            ],
        ]);

        $client = $this->createMock(AnthropicApiClient::class);
        $client->method('send')->willReturn(['ok' => true, 'text' => $resposta, 'error' => null]);
        $client->method('parseJson')->willReturn(json_decode($resposta, true));

        $r = (new PropostaTemplateImportador($client))->analisar('x', 'image/png');

        $this->assertTrue($r['ok']);
        $this->assertSame(15, $r['data']['validade_dias']);
        $this->assertCount(2, $r['data']['campos']);
        $this->assertSame('Garantia', $r['data']['campos'][1]['titulo']);
        $this->assertSame('', $r['data']['campos'][1]['texto']);
    }

    public function test_recusa_arquivo_que_nao_e_imagem(): void
    {
        $r = (new PropostaTemplateImportador($this->createMock(AnthropicApiClient::class)))->analisar('x', 'application/pdf');

        $this->assertFalse($r['ok']);
    }

    public function test_proposta_copia_cabecalho_e_campos_do_template(): void
    {
        $template = new PropostaComercialTemplate([
            'default_terms' => 'T',
            'cabecalho' => 'Locadora X',
            'campos' => [['titulo' => 'Garantia', 'texto' => '12 meses']],
        ]);

        $proposta = new PropostaComercial;
        $proposta->fillFromTemplate($template);

        $this->assertSame('Locadora X', $proposta->cabecalho);
        $this->assertSame('Garantia', $proposta->campos[0]['titulo']);
    }
}
