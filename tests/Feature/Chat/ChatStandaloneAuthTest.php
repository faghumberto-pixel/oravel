<?php

namespace Tests\Feature\Chat;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatStandaloneAuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithChatPlan(): User
    {
        $plan = Plan::create([
            'name' => 'Plano Chat '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['modulo_chat'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Chat '.uniqid(), 'slug' => 'tenant-chat-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Usuário Chat', 'email' => 'chat-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('senha-teste-123'), 'tenant_id' => $tenant->id,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    public function test_chat_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('chat.login'));

        $response->assertOk();
        $response->assertSee('Oravel Chat');
    }

    public function test_guest_sees_login_form_when_accessing_chat_without_redirect(): void
    {
        // GET /chat (start_url do manifest-chat.json) nunca deve devolver
        // um redirect HTTP -- PWAs instalados (display: standalone) travam
        // em tela preta na primeira abertura ao seguir um redirect no
        // start_url em vários WebViews Android/iOS (bug real 2026-08-12).
        // O próprio componente decide mostrar o login, mesmo response 200.
        $response = $this->get(route('chat.index'));

        $response->assertOk();
        $response->assertSee('Oravel Chat');
        $response->assertSee(route('chat.login'), false);
    }

    public function test_user_can_authenticate_via_chat_login_and_reach_chat_index(): void
    {
        $user = $this->makeUserWithChatPlan();

        $response = $this->post(route('chat.login'), [
            'email' => $user->email,
            'password' => 'senha-teste-123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('chat.index'));
    }

    public function test_login_always_sets_remember_token_regardless_of_form_input(): void
    {
        // Pedido explícito: sessão do chat não deve expirar sozinha, só por
        // logout manual ou reinício do aparelho -- por isso o controller
        // força remember=true mesmo sem checkbox no formulário.
        $user = $this->makeUserWithChatPlan();

        $this->post(route('chat.login'), [
            'email' => $user->email,
            'password' => 'senha-teste-123',
        ]);

        $user->refresh();
        $this->assertNotNull($user->remember_token);
    }

    public function test_authenticated_user_sees_chat_index(): void
    {
        $user = $this->makeUserWithChatPlan();

        $response = $this->actingAs($user)->get(route('chat.index'));

        $response->assertOk();
    }

    public function test_chat_logout_redirects_to_chat_login_not_admin(): void
    {
        $user = $this->makeUserWithChatPlan();

        $response = $this->actingAs($user)->post(route('chat.logout'));

        $response->assertRedirect(route('chat.login'));
        $this->assertGuest();
    }

    public function test_chat_index_shows_logout_button_when_authenticated(): void
    {
        $user = $this->makeUserWithChatPlan();

        $response = $this->actingAs($user)->get(route('chat.index'));

        $response->assertOk();
        $response->assertSee(route('chat.logout'), false);
    }
}
