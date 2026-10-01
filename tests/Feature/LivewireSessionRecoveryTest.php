<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ImplementationChargeResource\Pages\ListImplementationCharges;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sessão expirada (419) no Livewire não pode abrir o confirm() em inglês nem
 * deixar o resumo de Implantações em branco.
 */
class LivewireSessionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_panels_ship_the_419_recovery_script(): void
    {
        foreach (['/admin/login', '/central/login'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee("window.Livewire.hook('request'", false)
                ->assertSee('A página perdeu a conexão', false);
        }
    }

    public function test_implantacoes_summary_renders_with_the_page_without_lazy_loading(): void
    {
        $super = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br', 'password' => bcrypt('teste123'), 'tenant_id' => null]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        Livewire::test(ListImplementationCharges::class)
            ->assertSee('A receber')
            ->assertSee('Recebido')
            ->assertSee('Em atraso');
    }
}
