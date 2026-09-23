<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\PlanResource\Pages\ListPlans;
use App\Models\Plan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pedido do usuário 2026-09-23: sem mais plano padrão A/B/C, cada cliente
 * ganha um Plano próprio (negociado individualmente) e o vendedor manda o
 * link de assinatura (/assinar?plano={id}) direto pra ele. Sem essa ação,
 * não tinha como pegar esse link sem montar a URL na mão com o UUID do
 * plano -- ver PlanResource::table().
 */
class PlanResourceCopyLinkTest extends TestCase
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

    public function test_copy_signup_link_action_shows_the_correct_url(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $plan = Plan::create([
            'name' => 'Plano Negociado Cliente X', 'price' => 850, 'base_price' => 850, 'level' => 2,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_clients'],
        ]);

        Livewire::test(ListPlans::class)
            ->callTableAction('copy_signup_link', $plan)
            ->assertNotified();

        $expectedLink = route('checkout.create', ['plano' => $plan->id]);
        $this->assertStringContainsString('/assinar?plano='.$plan->id, $expectedLink);
    }

    public function test_signup_link_route_preselects_the_specific_negotiated_plan(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Negociado Cliente Y', 'price' => 1200, 'base_price' => 1200, 'level' => 3,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_clients'],
        ]);

        $link = route('checkout.create', ['plano' => $plan->id]);
        $response = $this->get($link);

        $response->assertOk();
        $response->assertSee($plan->name);
    }
}
