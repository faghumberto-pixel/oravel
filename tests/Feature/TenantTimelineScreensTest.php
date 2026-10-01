<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\TenantEventResource\Pages\ListTenantEvents;
use App\Filament\Central\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Central\Resources\TenantResource\RelationManagers\EventsRelationManager;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Consulta do histórico do cliente na Central: aba na empresa e lista geral. */
class TenantTimelineScreensTest extends TestCase
{
    use RefreshDatabase;

    private function actAsSuperAdmin(): User
    {
        $super = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br', 'password' => bcrypt('teste123'), 'tenant_id' => null]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        return $super;
    }

    private function tenant(): Tenant
    {
        $plan = Plan::create(['name' => 'Plano Tela', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);

        return Tenant::create(['name' => 'Cliente Tela', 'slug' => 'cliente-tela-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'trial']);
    }

    public function test_history_tab_lists_events_and_lets_the_operator_add_a_note(): void
    {
        $this->actAsSuperAdmin();
        $tenant = $this->tenant();
        $cadastro = TenantEvent::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

        Livewire::test(EventsRelationManager::class, ['ownerRecord' => $tenant, 'pageClass' => EditTenant::class])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$cadastro])
            ->callTableAction('create', data: ['title' => 'Ligação com o Marivan', 'description' => 'Combinou o treinamento'])
            ->assertHasNoTableActionErrors();

        $nota = TenantEvent::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('event_type', TenantEvent::ANOTACAO)->sole();
        $this->assertSame('Ligação com o Marivan', $nota->title);
        $this->assertNotNull($nota->actor_user_id);
    }

    public function test_global_history_lists_events_of_all_clients(): void
    {
        $this->actAsSuperAdmin();
        $a = $this->tenant();
        $b = $this->tenant();
        $eventos = TenantEvent::withoutGlobalScopes()->whereIn('tenant_id', [$a->id, $b->id])->get();

        Livewire::test(ListTenantEvents::class)->assertSuccessful()->assertCanSeeTableRecords($eventos);
    }
}
