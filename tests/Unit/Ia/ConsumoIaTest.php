<?php

namespace Tests\Unit\Ia\Stub {
    use App\Support\Ia\ConsumoIa;

    // O nome da classe e' o que identifica o aplicativo (class_basename), entao os stubs imitam os servicos reais.
    class StockAnalysisService
    {
        public function chama(): array
        {
            return ConsumoIa::contextoDoChamador();
        }
    }

    class OsPhotoOcrService
    {
        public function chama(): array
        {
            return ConsumoIa::contextoDoChamador();
        }
    }

    class ProcessWhatsAppMessageJob
    {
        public function chama(): array
        {
            return ConsumoIa::contextoDoChamador();
        }
    }

    class ServicoQueNinguemMapeou
    {
        public function chama(): array
        {
            return ConsumoIa::contextoDoChamador();
        }
    }
}

namespace Tests\Unit\Ia {
    use App\Support\Ia\ConsumoIa;
    use PHPUnit\Framework\TestCase;
    use Tests\Unit\Ia\Stub\OsPhotoOcrService;
    use Tests\Unit\Ia\Stub\ProcessWhatsAppMessageJob;
    use Tests\Unit\Ia\Stub\ServicoQueNinguemMapeou;
    use Tests\Unit\Ia\Stub\StockAnalysisService;

    class ConsumoIaTest extends TestCase
    {
        public function test_area_vem_do_servico_que_chamou(): void
        {
            $this->assertSame(['area' => 'almoxarifado'], (new StockAnalysisService)->chama());
            $this->assertSame(['area' => 'manutencao'], (new OsPhotoOcrService)->chama());
            $this->assertSame(['area' => 'whatsapp'], (new ProcessWhatsAppMessageJob)->chama());
        }

        public function test_servico_nao_mapeado_cai_em_outros(): void
        {
            $this->assertSame(['area' => 'outros'], (new ServicoQueNinguemMapeou)->chama());
        }

        public function test_toda_area_mapeada_tem_rotulo_no_painel(): void
        {
            foreach (['suprimentos', 'almoxarifado', 'comercial', 'financeiro', 'manutencao', 'prospects'] as $area) {
                $this->assertArrayHasKey($area, ConsumoIa::AREAS);
            }

            $this->assertSame('Manutenção', ConsumoIa::rotulo('manutencao'));
            $this->assertSame('Algo_novo', ConsumoIa::rotulo('algo_novo')); // area futura nao quebra o painel
        }
    }
}
