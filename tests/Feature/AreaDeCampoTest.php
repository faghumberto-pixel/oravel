<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Campo;
use App\Support\Pacotes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class AreaDeCampoTest extends TestCase
{
    use DatabaseTransactions;

    private const CELULAR = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

    private function admin(string $pacote, bool $campo): User
    {
        $plano = Pacotes::planoDeVitrine($pacote);
        $plano->update(['is_active' => true, 'features' => array_fill_keys(Pacotes::features($pacote), true)]);
        $tenant = Tenant::factory()->create(['plan_id' => $plano->id, 'campo_ativo' => $campo]);

        return TenantProvisioner::provision($tenant, ['name' => 'Maria Campo', 'email' => 'maria-'.uniqid().'@oravel.test', 'password' => 'senha12345']);
    }

    private function requisicao(string $agente, ?string $versao = null): Request
    {
        $r = Request::create('/admin/login', 'GET', [], $versao ? [Campo::COOKIE => $versao] : [], [], ['HTTP_USER_AGENT' => $agente]);

        return $r;
    }

    public function test_empresa_existente_nasce_com_campo_desligado_e_a_tela_nao_existe_para_ela(): void
    {
        $this->assertFalse((bool) Tenant::factory()->create()->fresh()->campo_ativo);

        $this->actingAs($this->admin('manutencao', false))->get('/campo')->assertNotFound();
    }

    public function test_campo_ligado_mostra_os_atalhos_do_produto_com_a_cor_dele(): void
    {
        $this->actingAs($this->admin('manutencao', true))->get('/campo')->assertOk()
            ->assertSee('Minhas OS do dia')->assertSee('Agenda')->assertDontSee('Visitas e ligações')
            ->assertSee('background-color: #ea580c', false)->assertSee('Abrir a versão completa');

        $this->actingAs($this->admin('vendas', true))->get('/campo')->assertOk()
            ->assertSee('Visitas e ligações')->assertDontSee('Minhas OS do dia')->assertSee('background-color: #db2777', false);
    }

    public function test_so_o_celular_com_campo_ligado_e_sem_escolha_manual_abre_no_campo(): void
    {
        $ligado = $this->admin('manutencao', true);
        $desligado = $this->admin('manutencao', false);

        $this->assertTrue(Campo::deveAbrirNoLogin($ligado, $this->requisicao(self::CELULAR)));
        $this->assertFalse(Campo::deveAbrirNoLogin($ligado, $this->requisicao('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120')), 'desktop continua no painel completo');
        $this->assertFalse(Campo::deveAbrirNoLogin($desligado, $this->requisicao(self::CELULAR)), 'empresa com campo desligado não muda nada');
        $this->assertFalse(Campo::deveAbrirNoLogin($ligado, $this->requisicao(self::CELULAR, 'completa')), 'quem escolheu a versão completa fica nela');
        $this->assertTrue(Campo::deveAbrirNoLogin($ligado, $this->requisicao(self::CELULAR, 'campo')));
    }

    public function test_alternar_guarda_a_escolha_e_exige_login(): void
    {
        $this->get('/campo')->assertRedirect();

        $this->actingAs($this->admin('manutencao', true))->get('/campo/versao/completa')
            ->assertRedirect(url('/admin'))->assertCookie(Campo::COOKIE, 'completa');
        $this->get('/campo/versao/campo')->assertRedirect(route('campo.home'))->assertCookie(Campo::COOKIE, 'campo');
        $this->get('/campo/versao/qualquer')->assertNotFound();
    }
}
