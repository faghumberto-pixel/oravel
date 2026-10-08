<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ContratoVisualizarPainelTest extends TestCase
{
    use DatabaseTransactions;

    private function cenario(string $rotulo): array
    {
        $plan = Plan::create([
            'name' => 'Plano '.$rotulo.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_contracts' => true, 'tabela_clients' => true, 'tabela_assets' => true],
        ]);
        $tenant = Tenant::create(['name' => 'Locadora '.$rotulo, 'slug' => 'loc-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $user = User::create([
            'name' => 'Admin '.$rotulo, 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'),
            'tenant_id' => $tenant->id, 'email_verified_at' => now(), 'is_approved' => true,
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['tenant_id' => $tenant->id]));
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente '.$rotulo, 'email' => 'c-'.uniqid().'@x.com', 'password' => bcrypt('x')]);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo '.$rotulo, 'status' => Asset::STATUS_DISPONIVEL]);
        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-VIS-'.$rotulo.'-'.uniqid(), 'start_date' => now(),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 1000,
        ]);

        return [$user, $contract];
    }

    public function test_admin_ve_o_contrato_como_pagina_para_imprimir(): void
    {
        [$admin, $contrato] = $this->cenario('A');

        $resposta = $this->actingAs($admin)->get(route('contratos.visualizar', $contrato->id));

        $resposta->assertOk();
        $resposta->assertSee($contrato->contract_number);
        $resposta->assertSee('Imprimir');
        $resposta->assertSee('Aguardando assinatura');
    }

    public function test_admin_nao_ve_contrato_de_outro_cliente(): void
    {
        [$adminA] = $this->cenario('A');
        [, $contratoB] = $this->cenario('B');

        $this->actingAs($adminA)->get(route('contratos.visualizar', $contratoB->id))->assertNotFound();
    }
}
