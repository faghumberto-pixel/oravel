<?php

namespace Tests\Feature;

use App\Filament\Pages\PainelGestao;
use App\Filament\Pages\PrimeirosPassos;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\AppProfile;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Caso real de 19/10/2026: a Central tirou "Painel: Dashboard" do contrato e o administrador continuava vendo o Painel de Controle e a Gestão à Vista. */
class PainelRespeitaContratoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(bool $comDashboard): User
    {
        $plano = Pacotes::planoDeVitrine('manutencao');
        $features = array_fill_keys(Pacotes::features('manutencao'), true);
        $features['modulo_dashboard'] = $comDashboard;
        $plano->update(['is_active' => true, 'features' => $features]);
        $t = Tenant::factory()->create(['plan_id' => $plano->id]);
        $u = TenantProvisioner::provision($t, ['name' => 'Maria', 'email' => 'm-'.uniqid().'@oravel.test', 'password' => 'senha12345']);
        $u->forceFill(['is_approved' => true])->save();

        return $u;
    }

    public function test_administrador_com_o_painel_no_contrato_acessa(): void
    {
        $this->actingAs($this->admin(true));

        $this->assertTrue(PainelGestao::canAccess());
    }

    public function test_administrador_sem_o_painel_no_contrato_nao_acessa_nem_pela_url(): void
    {
        $u = $this->admin(false);
        $this->actingAs($u);

        $this->assertFalse(PainelGestao::canAccess());
        $this->get(route('filament.admin.pages.painel-controle'))->assertForbidden();
    }

    public function test_sem_o_painel_a_pagina_inicial_do_administrador_nao_e_o_painel(): void
    {
        $u = $this->admin(false);
        $this->actingAs($u);

        $this->assertStringNotContainsString('painel-controle', AppProfile::homeUrl($u, AppProfile::ADMIN));
        $this->assertSame(PrimeirosPassos::getUrl(), AppProfile::homeUrl($u, AppProfile::ADMIN), 'cai no Guia dos módulos');
    }

    public function test_sem_o_painel_o_guia_vale_ate_antes_da_tela_inicial_do_pacote(): void
    {
        $u = $this->admin(false);
        $this->actingAs($u);

        // a tela inicial do pacote Manutenção é o Quadro de OS; sem o painel, o administrador abre o Guia dos módulos mesmo assim
        $this->assertNotNull(Pacotes::urlInicial($u->tenant), 'o pacote tem tela inicial própria');
        $this->assertSame(PrimeirosPassos::getUrl(), AppProfile::homeUrl($u, AppProfile::ADMIN));
    }
}
