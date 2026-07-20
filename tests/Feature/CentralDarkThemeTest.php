<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralDarkThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_panel_forces_dark_mode_with_slate_background(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();

        $this->actingAs($super);

        $response = $this->get('/central');
        $response->assertOk();

        $html = $response->getContent();

        // Filament aplica a classe "dark" na tag <html> quando o dark mode
        // e' forcado -- confirma que o painel realmente abre escuro sempre.
        $this->assertMatchesRegularExpression('/<html[^>]*class="[^"]*\bdark\b/', $html);

        // Cor customizada (slate) injetada via CSS custom properties no
        // <head> -- confirma que a paleta gray realmente foi trocada, nao
        // so' o dark mode ligado com a cinza padrao do Filament.
        $this->assertStringContainsString('15, 23, 42', $html);
        $this->assertStringContainsString('30, 41, 59', $html);
    }
}
