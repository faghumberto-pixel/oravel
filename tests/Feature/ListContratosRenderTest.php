<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ContratoResource\Pages\ListContratos;
use App\Models\Plan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ListContratosRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_page_renders_without_error(): void
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
        Filament::setCurrentPanel(Filament::getPanel('central'));

        Plan::create([
            'name' => 'Plano Render', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_clients'],
        ]);

        Livewire::test(ListContratos::class)->assertOk();
    }
}
