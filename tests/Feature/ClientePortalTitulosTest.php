<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** O título de cada tela do Portal do Cliente é o mesmo nome do item do menu. */
class ClientePortalTitulosTest extends TestCase
{
    use DatabaseTransactions;

    public function test_titulo_da_tela_acompanha_o_menu(): void
    {
        $plan = Plan::create(['name' => 'Plano PT '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_clients' => true]]);
        $t = Tenant::create(['name' => 'Locadora PT', 'slug' => 'pt-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $c = Client::create(['tenant_id' => $t->id, 'name' => 'Cliente PT', 'email' => 'c-'.uniqid().'@t.com', 'password' => 'senha123']);
        $c->forceFill(['portal_access_enabled_at' => now()])->save();

        $telas = [
            'inicio' => 'Início', 'abrir-chamado' => 'Abrir Chamado', 'atualizar-horimetro' => 'Atualizar Horímetro', 'fale-conosco' => 'Reclamação, Sugestão ou Elogio',
            'meu-financeiro' => 'Meu Financeiro', 'meus-ativos' => 'Meus Ativos', 'meus-contratos' => 'Meus Contratos', 'minhas-mensagens' => 'Mensagens',
            'minhas-os' => 'Minhas OS', 'solicitar-equipamento' => 'Solicitar Equipamento', 'solicitar-retirada' => 'Solicitar Retirada', 'meus-documentos' => 'Documentos',
        ];

        foreach ($telas as $slug => $titulo) {
            $html = $this->actingAs($c, 'client')->get('/cliente/'.$slug)->assertOk()->getContent();
            preg_match('#<h1[^>]*>(.*?)</h1>#s', $html, $h);

            $this->assertSame($titulo, trim(strip_tags($h[1] ?? '')), "título da tela /cliente/{$slug}");
        }
    }
}
