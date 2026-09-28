<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /login (GET e POST) sao scaffolding padrao do Breeze, nunca usados de
 * verdade -- o login real deste app e' App\Filament\Pages\Auth\Login
 * (mesmo raciocinio de RegistrationTest pro /register). Rota fechada
 * (redireciona pro login do painel em vez de autenticar) -- pedido do
 * usuario 2026-09-28.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_redirects_to_panel_login(): void
    {
        $response = $this->get('/login');

        $response->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_posting_to_login_does_not_authenticate(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('filament.admin.auth.login'));
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
