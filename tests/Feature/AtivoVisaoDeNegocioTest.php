<?php

namespace Tests\Feature;

use Tests\TestCase;

/** A aba "Visão de negócio" do cadastro completo de ativos mostra o resumo da tela simples (quem é, status, cliente, valores). */
class AtivoVisaoDeNegocioTest extends TestCase
{
    private function ativo(array $o = []): object
    {
        return new class($o)
        {
            public $name = 'Plataforma Haulotte H12SX';

            public $patrimonio = 'PAT-0004';

            public $asset_category = 'Plataforma Elevatória';

            public $description = 'Plataforma de 12 m, uso em obra.';

            public $status = 'locado';

            public $acquisition_value = 124000;

            public $horimetro_atual = 1849;

            public $checklistGroup;

            public $client;

            public function __construct(array $o)
            {
                $this->checklistGroup = (object) ['name' => 'Equipamentos de Elevação'];
                $this->client = isset($o['semCliente']) ? null : (object) ['name' => 'Indústria Metalúrgica Paulista S/A'];
            }

            public function getDepreciationData(): array
            {
                return ['current_value' => 98500.5];
            }

            public function getFirstMediaUrl($c = '', $conv = ''): string
            {
                return '';
            }
        };
    }

    public function test_mostra_identificacao_status_cliente_e_valores(): void
    {
        $html = view('filament.partials.asset-visao-negocio', ['ativo' => $this->ativo()])->render();

        foreach (['Plataforma Haulotte H12SX', 'PAT-0004', 'Equipamentos de Elevação', 'Plataforma Elevatória', 'Plataforma de 12 m, uso em obra.', 'Locado', 'Indústria Metalúrgica Paulista S/A', 'R$ 124.000,00', 'R$ 98.500,50', '1.849 h'] as $trecho) {
            $this->assertStringContainsString($trecho, $html);
        }
    }

    public function test_sem_cliente_e_sem_foto_nao_quebra(): void
    {
        $html = view('filament.partials.asset-visao-negocio', ['ativo' => $this->ativo(['semCliente' => true])])->render();

        $this->assertStringContainsString('Cliente atual', $html);
        $this->assertStringNotContainsString('<img', $html);
    }
}
