<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ContratoResource;
use App\Support\MenuModules;
use App\Support\SaaSRegistry;
use Tests\TestCase;

class MenuModulesTest extends TestCase
{
    public function test_menus_pai_resolvem_para_a_chave_do_contrato(): void
    {
        $this->assertSame('menu_relatorios_analises', MenuModules::keyForItem('Relatórios', 'Análises'));
        $this->assertSame('menu_relatorios_analises', MenuModules::keyForSlug('relatorios-analises'));
        $this->assertNull(MenuModules::keyForItem('Relatórios', 'Item que nao existe'));
    }

    public function test_modulos_herdados_existem_no_registro(): void
    {
        $conhecidas = collect(SaaSRegistry::modules())->pluck('feature')->filter()->all();

        foreach (MenuModules::all() as $chave => $menu) {
            foreach ($menu['herda'] as $herdada) {
                $this->assertContains($herdada, $conhecidas, "{$chave} herda {$herdada}, que nao existe no registro");
            }
        }
    }

    public function test_tela_de_contratos_lista_menus_e_nao_deixa_modulo_em_outros(): void
    {
        $opcoes = collect(ContratoResource::groupedFeatureOptions())->flatMap(fn ($o) => array_keys($o))->all();

        foreach (array_keys(MenuModules::all()) as $chave) {
            $this->assertContains($chave, $opcoes);
        }

        $this->assertArrayNotHasKey('Outros', ContratoResource::groupedFeatureOptions());
    }
}
