<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\TenantComplianceResource\Pages\ViewTenantCompliance;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bug real em PROD 2026-09-23 (500 ao abrir "Status de Conformidade" de
 * um tenant): a página usava Filament\Infolists\Components\BadgeEntry,
 * classe que não existe nessa versão do Filament (o jeito certo é
 * TextEntry::make(...)->badge()). Achado pelo usuário testando a tela.
 */
class ViewTenantComplianceRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_page_renders_without_error(): void
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

        $plan = Plan::create([
            'name' => 'Plano Compliance', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Compliance', 'slug' => 'tenant-compliance-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        Livewire::test(ViewTenantCompliance::class, ['record' => $tenant->getKey()])->assertOk();
    }
}
