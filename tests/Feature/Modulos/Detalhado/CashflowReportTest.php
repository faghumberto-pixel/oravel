<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Filament\Pages\CashflowPage;
use App\Models\CashflowReport;
use App\Support\SaaSRegistry;
use Livewire\Livewire;

/**
 * Fluxo de Caixa: o modulo e a tela CashflowPage. O Model CashflowReport e so
 * um marcador (nao tem tabela) para o plano/permissoes.
 */
class CashflowReportTest extends PerfisTestCase
{
    public function test_modulo_esta_registrado_para_plano_e_permissoes(): void
    {
        $meta = SaaSRegistry::forModel(CashflowReport::class);

        $this->assertSame('tabela_cashflow_report', $meta['feature']);
        $this->assertNotEmpty($meta['slug']);
    }

    public function test_administrador_com_modulo_no_plano_abre_a_tela(): void
    {
        $this->actingAs($this->a['admin']);

        $this->assertTrue(CashflowPage::canAccess());
        Livewire::test(CashflowPage::class)->assertSuccessful();
    }

    public function test_modulo_fora_do_plano_nega_ate_o_administrador(): void
    {
        $semModulo = array_values(array_diff(
            array_filter(array_column(SaaSRegistry::modules(), 'feature')),
            ['tabela_cashflow_report']
        ));
        [, $perfis] = $this->montarTenant($semModulo);
        $this->actingAs($perfis['admin']);

        $this->assertFalse(CashflowPage::canAccess());
    }

    public function test_tecnico_e_colaborador_sem_permissao_nao_veem_o_fluxo_de_caixa(): void
    {
        foreach (['tecnico', 'colaborador'] as $perfil) {
            $this->actingAs($this->a[$perfil]);

            $this->lacunaConhecida(
                "CashflowPage::canAccess() so confere o plano, nao a permissao do usuario: o {$perfil} do tenant abre o fluxo de caixa",
                fn () => $this->assertFalse(CashflowPage::canAccess(), $perfil)
            );
        }
    }
}
