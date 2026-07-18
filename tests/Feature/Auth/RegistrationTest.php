<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /register (GET e POST) sao scaffolding padrao do Breeze, nunca usados
 * de verdade -- o cadastro real deste app e' tenant-aware e exige
 * aprovacao (App\Filament\Pages\Auth\Login::register()). O
 * RegisteredUserController antigo criava usuario SEM tenant_id e SEM
 * is_approved=false, ignorando as duas travas -- rota fechada
 * (redireciona pro login do painel em vez de completar o cadastro).
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_redirects_to_panel_login(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_posting_to_register_does_not_create_a_user(): void
    {
        $countBefore = User::count();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('filament.admin.auth.login'));
        $this->assertGuest();
        $this->assertSame($countBefore, User::count());
    }
}
