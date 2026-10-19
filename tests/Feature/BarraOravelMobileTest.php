<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\TenantProvisioner;
use App\Support\Pacotes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Toda tela de celular tem a barra com as 3 bolinhas e o nome Oravel. */
class BarraOravelMobileTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(string $pacote)
    {
        $plano = Pacotes::planoDeVitrine($pacote);
        $plano->update(['is_active' => true, 'features' => array_fill_keys(Pacotes::features($pacote), true)]);
        $tenant = Tenant::factory()->create(['plan_id' => $plano->id, 'campo_ativo' => true]);
        $admin = TenantProvisioner::provision($tenant, ['name' => 'Maria', 'email' => 'm-'.uniqid().'@oravel.test', 'password' => 'senha12345']);
        $admin->forceFill(['is_approved' => true])->save();

        return $admin;
    }

    public function test_telas_de_celular_mostram_as_tres_bolinhas(): void
    {
        $this->actingAs($this->admin('manutencao'));

        foreach (['/campo', '/admin/os-rapida', '/admin/agenda-tecnico/mobile'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('data-testid="barra-oravel"', $html, $url);
            $this->assertStringContainsString('background:#ef4444', $html, $url);
            $this->assertStringContainsString('background:#22c55e', $html, $url);
        }

        $this->actingAs($this->admin('vendas'));
        $this->assertStringContainsString('data-testid="barra-oravel"', $this->get('/admin/crm/leads/mobile')->assertOk()->getContent());
    }

    public function test_painel_no_celular_tem_as_bolinhas_no_topo_e_so_ali_no_admin(): void
    {
        $this->actingAs($this->admin('manutencao'));
        $html = $this->get(route('filament.admin.pages.painel-controle'))->assertOk()->getContent();

        $this->assertStringContainsString('lg:hidden', $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'background:#ef4444'), 'bolinhas no menu lateral e no topo do celular');
    }
}
