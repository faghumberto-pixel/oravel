<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ContratoResource;
use Tests\TestCase;

/** A lista de módulos do Contrato (Central) acompanha o menu: mover uma tela não pode mudar o grupo do módulo dela. */
class ContratoModulosAposMudancaDeMenuTest extends TestCase
{
    public function test_modulo_de_ativos_continua_no_grupo_ativos_mesmo_com_vencimento_de_veiculos_em_logistica(): void
    {
        $grupos = ContratoResource::groupedFeatureOptions();

        $this->assertArrayHasKey('tabela_assets', $grupos['Ativos'] ?? [], 'Ativos / Frota deve ficar em "Ativos".');
        $this->assertArrayNotHasKey('tabela_assets', $grupos['Logística'] ?? [], 'Ativos / Frota não pode ir para "Logística".');
    }
}
