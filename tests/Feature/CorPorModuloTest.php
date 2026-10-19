<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Pacotes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Cada produto contratado dá a sua cor ao menu, ao topo e ao destaque do sistema (desktop e app); Locadora segue no padrão. */
class CorPorModuloTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(string $pacote, array $pacotes = []): User
    {
        $plano = Pacotes::planoDeVitrine($pacote);
        $plano->update(['is_active' => true, 'pacotes' => $pacotes ?: null, 'features' => array_fill_keys(Pacotes::featuresDeProdutos($pacotes ?: [$pacote]), true)]);
        $t = Tenant::factory()->create(['plan_id' => $plano->id]);
        $u = TenantProvisioner::provision($t, ['name' => 'Maria', 'email' => 'm-'.uniqid().'@oravel.test', 'password' => 'senha12345']);
        $u->forceFill(['is_approved' => true])->save();

        return $u;
    }

    public function test_cada_produto_tem_a_cor_do_app_do_site_e_variacoes_escuras(): void
    {
        $esperadas = ['manutencao' => '#ea580c', 'servicos' => '#2563eb', 'logistica' => '#0d9488', 'estoque' => '#7c3aed', 'vendas' => '#db2777', 'departamento_pessoal' => '#0ea5e9'];

        foreach ($esperadas as $codigo => $cor) {
            $tema = Pacotes::tema($this->admin($codigo)->tenant);
            $this->assertSame($cor, $tema['cor'], $codigo);
            $this->assertNotSame($cor, $tema['menu']);
        }
    }

    public function test_locadora_e_varios_produtos_ficam_no_visual_padrao(): void
    {
        $this->assertNull(Pacotes::tema($this->admin('locadora')->tenant));
        $this->assertNull(Pacotes::tema($this->admin('servicos', ['servicos', 'vendas'])->tenant));
    }

    public function test_escurecer_mistura_com_preto(): void
    {
        $this->assertSame('#000000', Pacotes::escurecer('#ea580c', 1.0));
        $this->assertSame('#ea580c', Pacotes::escurecer('#ea580c', 0.0));
        $this->assertSame('#752c06', Pacotes::escurecer('#ea580c', 0.5));
    }

    public function test_desktop_fica_no_visual_padrao_para_todos_e_so_o_app_do_celular_leva_a_cor(): void
    {
        $u = $this->admin('manutencao');
        $u->tenant->forceFill(['campo_ativo' => true])->save();
        $this->actingAs($u);

        $desktop = $this->get(route('filament.admin.pages.maintenance-kanban'))->getContent();
        $this->assertStringNotContainsString('data-tema=', $desktop, 'o painel do desktop não recebe a cor do módulo');

        $campo = $this->get('/campo');
        $campo->assertOk();
        $this->assertStringContainsString('background:'.Pacotes::tema(auth()->user()->tenant)['topo'], $campo->getContent(), 'a barra do app leva a cor do módulo');
    }
}
