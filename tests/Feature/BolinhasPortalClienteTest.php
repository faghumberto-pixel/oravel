<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** O portal do cliente também leva as 3 bolinhas (vermelha, amarela, verde) e o nome Oravel no menu lateral. */
class BolinhasPortalClienteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_menu_do_portal_tem_as_tres_bolinhas_e_o_nome_oravel(): void
    {
        $plano = Plan::create(['name' => 'P'.uniqid(), 'price' => 1, 'is_active' => true, 'features' => ['tabela_clients' => true, 'modulo_portal_cliente' => true]]);
        $t = Tenant::factory()->create(['plan_id' => $plano->id, 'payment_method' => 'cartao']);
        $c = Client::create(['tenant_id' => $t->id, 'name' => 'Cliente Portal', 'email' => 'c-'.uniqid().'@t.com', 'password' => 'senha123']);
        $c->forceFill(['portal_access_enabled_at' => now()])->save();

        $html = $this->actingAs($c, 'client')->get('/cliente/abrir-chamado')->assertOk()->getContent();

        $this->assertGreaterThanOrEqual(2, substr_count($html, 'background:#ef4444'), 'bolinhas no menu lateral e no topo do celular');
        $this->assertStringContainsString('background:#f59e0b', $html);
        $this->assertStringContainsString('background:#22c55e', $html);
        $this->assertStringContainsString('>Oravel</span>', $html);
    }
}
