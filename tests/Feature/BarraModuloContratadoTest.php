<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\TenantProvisioner;
use App\Support\Pacotes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BarraModuloContratadoTest extends TestCase
{
    use DatabaseTransactions;

    private function paginaComoAdmin(string $pacote, array $pacotes = []): string
    {
        $plano = Pacotes::planoDeVitrine($pacote);
        $plano->update(['is_active' => true, 'pacotes' => $pacotes ?: null, 'features' => array_fill_keys(Pacotes::featuresDeProdutos($pacotes ?: [$pacote]), true)]);
        $tenant = Tenant::factory()->create(['plan_id' => $plano->id, 'name' => 'Empresa da Barra']);
        $admin = TenantProvisioner::provision($tenant, ['name' => 'Maria', 'email' => 'm-'.uniqid().'@oravel.test', 'password' => 'senha12345']);

        $admin->forceFill(['is_approved' => true])->save();

        return $this->actingAs($admin)->get(route('filament.admin.pages.painel-controle'))->assertOk()->getContent();
    }

    public function test_topbar_mostra_o_modulo_na_frente_do_nome_e_o_logo_fica_no_menu_lateral_ao_lado_das_bolinhas(): void
    {
        $html = $this->paginaComoAdmin('manutencao');

        $this->assertStringContainsString('Empresa da Barra', $html);
        $this->assertStringContainsString('Módulo: Manutenção', $html);
        $this->assertSame(1, substr_count($html, 'images/oravel-logo-menu.png'), 'o logotipo aparece uma vez só, no menu lateral');
    }

    public function test_com_mais_de_um_produto_lista_todos(): void
    {
        $this->assertStringContainsString('Módulo: Manutenção + Vendas', $this->paginaComoAdmin('manutencao', ['manutencao', 'vendas']));
    }
}
