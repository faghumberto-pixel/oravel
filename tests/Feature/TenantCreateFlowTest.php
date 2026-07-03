<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TenantCreateFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_tenant_via_the_central_panel_form_provisions_its_admin(): void
    {
        $plan = Plan::create([
            'name' => 'Plano fluxo completo', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_clients'],
        ]);

        $super = User::create([
            'name' => 'Super', 'email' => 'humberto@oravel.com.br',
            'password' => 'x', 'role' => 'admin', 'hourly_rate' => 0,
        ]);
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $slug = 'fluxo-completo-' . uniqid();

        Livewire::test(\App\Filament\Central\Resources\TenantResource\Pages\CreateTenant::class)
            ->fillForm([
                'name' => 'Empresa Fluxo Completo',
                'slug' => $slug,
                'plan_id' => $plan->id,
                'status' => 'active',
                'admin_name' => 'Admin da Empresa',
                'admin_email' => 'admin-fluxo@oravel.com.br',
                'admin_password' => 'senha12345',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tenant = \App\Models\Tenant::where('slug', $slug)->first();
        $this->assertNotNull($tenant, 'Tenant foi criado');

        $admin = User::where('email', 'admin-fluxo@oravel.com.br')->first();
        $this->assertNotNull($admin, 'Usuario admin foi provisionado');
        $this->assertSame($tenant->id, $admin->tenant_id);
        $this->assertTrue($admin->isAdmin());
    }
}
