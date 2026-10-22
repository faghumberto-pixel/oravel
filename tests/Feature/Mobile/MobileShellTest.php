<?php

namespace Tests\Feature\Mobile;

use App\Filament\Pages\AppColaborador;
use App\Filament\Pages\MaintenanceOrderListPage;
use App\Support\AppProfile;
use App\Support\MobileNav;
use Livewire\Livewire;
use Tests\Feature\Modulos\Detalhado\PerfisTestCase;

/**
 * Padrao visual dos apps mobile (x-mobile.shell + x-mobile.bottom-nav): a barra
 * de icones do rodape muda por perfil e por permissao, e as telas que usam o
 * padrao renderizam com ela.
 *
 * Rode com DB_DATABASE descartavel (ver PerfisTestCase).
 */
class MobileShellTest extends PerfisTestCase
{
    private function chaves(string $perfil): array
    {
        $this->actingAs($this->a[$perfil]);

        return array_column(MobileNav::itens($this->a[$perfil]), 'key');
    }

    public function test_administrador_ve_os_atalhos_do_dia_a_dia(): void
    {
        $chaves = $this->chaves('admin');

        $this->assertSame('inicio', $chaves[0]);
        $this->assertContains('os', $chaves);
        $this->assertContains('agenda', $chaves);
        $this->assertSame('mais', end($chaves));
        $this->assertLessThanOrEqual(MobileNav::MAX_ITENS, count($chaves));
    }

    public function test_tecnico_ve_os_e_agenda_mas_nao_clientes(): void
    {
        $chaves = $this->chaves('tecnico');

        $this->assertContains('os', $chaves);
        $this->assertContains('agenda', $chaves);
        $this->assertNotContains('clientes', $chaves);
    }

    public function test_colaborador_sem_permissao_so_ve_o_inicio(): void
    {
        $this->assertSame(['inicio'], $this->chaves('colaborador'));
    }

    public function test_item_aparece_quando_a_permissao_e_concedida(): void
    {
        $this->conceder($this->a['colaborador'], 'ponto', ['ler']);
        $chaves = $this->chaves('colaborador');

        $this->assertContains('ponto', $chaves);
    }

    public function test_inicio_aponta_para_a_tela_inicial_do_perfil(): void
    {
        foreach (['admin', 'tecnico', 'colaborador'] as $perfil) {
            $this->actingAs($this->a[$perfil]);
            $inicio = MobileNav::itens($this->a[$perfil])[0];

            $this->assertSame(AppProfile::homeUrl($this->a[$perfil], AppProfile::for($this->a[$perfil])), $inicio['url'], $perfil);
        }
    }

    public function test_telas_do_padrao_mostram_a_barra_de_icones(): void
    {
        $this->actingAs($this->a['admin']);

        Livewire::test(MaintenanceOrderListPage::class)->assertSuccessful()
            ->assertSee('Navegação principal', false)->assertSee('Ordens de Serviço');
        Livewire::test(AppColaborador::class)->assertSuccessful()
            ->assertSee('Navegação principal', false)->assertSee('App do Colaborador');
    }

    public function test_componentes_do_padrao_nao_usam_classes_gray(): void
    {
        foreach (glob(resource_path('views/components/mobile/{shell,bottom-nav,menu-item}.blade.php'), GLOB_BRACE) as $arquivo) {
            $this->assertDoesNotMatchRegularExpression(
                '/\b(?:bg|text|border|placeholder|ring)-gray-\d+/',
                file_get_contents($arquivo),
                basename($arquivo).': variaveis --gray-N do Filament nao existem no layout mobile; use zinc-*'
            );
        }
    }
}
