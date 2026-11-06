<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Página pública do site que gera a O.S. em branco para imprimir: numeração, limites dos parâmetros e escape do que o visitante digita. */
class SiteOsParaImprimirTest extends TestCase
{
    private function gerar(array $get): string
    {
        $arquivo = base_path('public_html/inteligencia-artificial/os-para-imprimir.php');
        $codigo = '$_GET = '.var_export($get, true).'; include '.var_export($arquivo, true).';';

        $p = new Process([PHP_BINARY, '-d', 'display_errors=1', '-d', 'error_reporting=E_ALL', '-r', $codigo]);
        $p->run();

        $this->assertSame(0, $p->getExitCode(), $p->getErrorOutput().$p->getOutput());
        $this->assertStringNotContainsString('Warning:', $p->getOutput());
        $this->assertStringNotContainsString('Notice:', $p->getOutput());
        $this->assertStringNotContainsString('Deprecated:', $p->getOutput());

        return $p->getOutput();
    }

    public function test_uma_folha_padrao_vem_numerada_com_todos_os_campos_que_a_ia_le(): void
    {
        $html = $this->gerar([]);

        $this->assertSame(1, substr_count($html, 'class="folha"'));
        $this->assertStringContainsString('<strong>0001</strong>', $html);
        foreach (['Cliente', 'Equipamento (código, patrimônio ou placa)', 'Horímetro ou km', 'Preventiva', 'Corretiva', 'Troca', 'Problema relatado', 'Serviço executado',
            'Peças e materiais usados', 'Mão de obra (R$)', 'Técnico responsável', 'Observações', 'Assinatura do técnico', 'Assinatura do cliente'] as $campo) {
            $this->assertStringContainsString($campo, $html, $campo);
        }
        $this->assertStringContainsString('noindex', $html);
    }

    public function test_copias_e_numero_inicial_geram_folhas_sequenciais(): void
    {
        $html = $this->gerar(['copias' => '3', 'inicio' => '41']);

        $this->assertSame(3, substr_count($html, 'class="folha"'));
        foreach (['0041', '0042', '0043'] as $n) {
            $this->assertStringContainsString("<strong>{$n}</strong>", $html);
        }
        $this->assertStringNotContainsString('<strong>0044</strong>', $html);
    }

    public function test_parametros_fora_do_limite_ou_invalidos_caem_nos_valores_seguros(): void
    {
        $this->assertSame(20, substr_count($this->gerar(['copias' => '999']), 'class="folha"'));
        $this->assertSame(1, substr_count($this->gerar(['copias' => '-5']), 'class="folha"'));
        $this->assertSame(1, substr_count($this->gerar(['copias' => 'abc']), 'class="folha"'));
        $this->assertStringContainsString('<strong>0001</strong>', $this->gerar(['inicio' => 'xyz']));
        $this->assertStringContainsString('<strong>99999</strong>', $this->gerar(['inicio' => '5000000']));
    }

    public function test_o_nome_da_empresa_e_escapado_e_limitado(): void
    {
        $html = $this->gerar(['empresa' => '<script>alert(1)</script> Locadora "Alvorada" & Cia']);

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; Locadora &quot;Alvorada&quot; &amp; Cia', $html);

        $longo = $this->gerar(['empresa' => str_repeat('A', 200)]);
        $this->assertStringContainsString(str_repeat('A', 60), $longo);
        $this->assertStringNotContainsString(str_repeat('A', 61), $longo);
    }

    public function test_a_pagina_de_ia_oferece_o_gerador_apontando_para_o_php(): void
    {
        $ia = file_get_contents(base_path('public_html/inteligencia-artificial/index.html'));

        $this->assertSame(1, substr_count($ia, '<!--os-manuscrita:inicio-->'));
        $this->assertStringContainsString('action="/inteligencia-artificial/os-para-imprimir.php"', $ia);
        $this->assertStringContainsString('name="copias"', $ia);
        $this->assertStringContainsString('name="inicio"', $ia);
        $this->assertStringContainsString('name="empresa"', $ia);
        $this->assertStringContainsString('id="os-manuscrita"', $ia);
    }
}
