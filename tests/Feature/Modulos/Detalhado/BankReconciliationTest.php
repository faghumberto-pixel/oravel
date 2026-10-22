<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Filament\Pages\BancaryReconciliationPage;
use App\Models\AccountReceivable;
use App\Models\BankReconciliation;
use App\Support\SaaSRegistry;
use Livewire\Livewire;

/**
 * Conciliacao Bancaria: o modulo e a tela BancaryReconciliationPage. O Model
 * BankReconciliation e so um marcador (nao tem tabela) para o plano/permissoes.
 * A tela exige o modulo no plano E permissao de ler Contas a Receber.
 */
class BankReconciliationTest extends PerfisTestCase
{
    private function slugReceber(): string
    {
        return SaaSRegistry::forModel(AccountReceivable::class)['slug'];
    }

    public function test_modulo_esta_registrado_para_plano_e_permissoes(): void
    {
        $meta = SaaSRegistry::forModel(BankReconciliation::class);

        $this->assertSame('tabela_bank_reconciliation', $meta['feature']);
        $this->assertNotEmpty($meta['slug']);
        $this->assertNotEmpty($meta['label']);
    }

    public function test_administrador_com_modulo_no_plano_abre_a_tela(): void
    {
        $this->actingAs($this->a['admin']);

        $this->assertTrue(BancaryReconciliationPage::canAccess());
        Livewire::test(BancaryReconciliationPage::class)->assertSuccessful();
    }

    public function test_tecnico_e_colaborador_sem_permissao_nao_abrem_a_tela(): void
    {
        foreach (['tecnico', 'colaborador'] as $perfil) {
            $this->actingAs($this->a[$perfil]);
            $this->assertFalse(BancaryReconciliationPage::canAccess(), $perfil);
        }
    }

    public function test_permissao_de_ler_contas_a_receber_libera_a_tela(): void
    {
        $this->conceder($this->a['tecnico'], $this->slugReceber(), ['ler']);
        $this->actingAs($this->a['tecnico']);

        $this->assertTrue(BancaryReconciliationPage::canAccess());
    }

    public function test_modulo_fora_do_plano_nega_ate_o_administrador(): void
    {
        $semModulo = array_values(array_diff(
            array_filter(array_column(SaaSRegistry::modules(), 'feature')),
            ['tabela_bank_reconciliation']
        ));
        [, $perfis] = $this->montarTenant($semModulo);
        $this->actingAs($perfis['admin']);

        $this->assertFalse(BancaryReconciliationPage::canAccess());
    }
}
