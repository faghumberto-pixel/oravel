<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ContratoResource\Pages\CreateContrato;
use App\Models\Plan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Caminho dedicado de gerar link de assinatura, separado de "Planos"
 * (2026-09-23, pedido do usuário). Por baixo cria o mesmo tipo de
 * registro (Plan) que PlanResource, só numa tela mais enxuta que já
 * mostra o link assim que o contrato é criado. Rebatizada de "Proposta"
 * pra "Contrato" no mesmo dia (pedido do usuário).
 */
class ContratoResourceTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();

        return $super;
    }

    public function test_creating_a_contrato_generates_a_working_signup_link(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('central'));

        // Módulos agora ficam divididos num CheckboxList por grupo de menu
        // (2026-09-23, "as tabelas assim como os menus estejam agrupadas");
        // 'tabela_clients' (Clientes) vive no grupo 'Comercial' -- ver
        // ContratoResource::groupFieldName().
        Livewire::test(CreateContrato::class)
            ->fillForm([
                'name' => 'Contrato Cliente Teste',
                'base_price' => 480,
                'billing_cycle' => 'monthly',
                'features_group_comercial' => ['tabela_clients'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $plan = Plan::where('name', 'Contrato Cliente Teste')->firstOrFail();

        $this->assertSame('480.00', $plan->base_price);
        $this->assertSame('480.00', $plan->price, 'price deve acompanhar base_price mesmo nao aparecendo no formulario');
        $this->assertTrue($plan->is_active);
        $this->assertSame(['tabela_clients'], $plan->features);

        // O link gerado precisa realmente funcionar -- ir na tela pública
        // de cadastro pre-selecionando esse contrato. /assinar fica sob
        // middleware 'guest': sem deslogar aqui, a sessao do operador
        // faria redirecionar em vez de mostrar a tela (mesmo achado do
        // teste de assinatura da Central).
        $this->post('/logout');
        $response = $this->get(route('checkout.create', ['plano' => $plan->id]));
        $response->assertOk();
        $response->assertSee('Contrato Cliente Teste');
    }
}
