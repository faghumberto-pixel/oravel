<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\TenantResource\Pages\EditTenant;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Achado do usuario (2026-07-08): ele restringiu o Plano do tenant Torres &
 * Guindastes pra so "tabela_assets", mas o tenant continuava com "modulo_chat"
 * liberado -- override aditivo gravado direto no Tenant (tela "Recursos
 * Adicionais"), que nao e' removido quando o Plano fica mais restrito. Nao e'
 * bug (comportamento documentado), mas e' facil de nao perceber. Este teste
 * cobre o aviso visual adicionado em TenantResource::form() pra deixar isso
 * obvio no proprio formulario.
 */
class TenantResourceOverrideWarningTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCentralSuper(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'humberto@oravel.com.br',
            'password' => 'x', 'role' => 'admin', 'hourly_rate' => 0,
        ]);
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));
    }

    public function test_warning_appears_when_tenant_override_exceeds_plan_features(): void
    {
        $this->actingAsCentralSuper();

        $plan = Plan::create([
            'name' => 'Plano Restrito '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Torres & Guindastes Teste', 'slug' => 'tg-teste-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active', 'features' => ['modulo_chat'],
        ]);

        Livewire::test(EditTenant::class, ['record' => $tenant->id])
            ->assertSee('só por override deste tenant');
    }

    public function test_no_warning_when_tenant_has_no_overrides_beyond_plan(): void
    {
        $this->actingAsCentralSuper();

        $plan = Plan::create([
            'name' => 'Plano Amplo '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'modulo_chat'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Sem Override', 'slug' => 'sem-override-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active', 'features' => [],
        ]);

        Livewire::test(EditTenant::class, ['record' => $tenant->id])
            ->assertDontSee('só por override deste tenant');
    }
}
