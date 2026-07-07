<?php

namespace Tests\Feature;

use App\Filament\Pages\CrmMapa;
use App\Filament\Widgets\CrmLeadMapWidget;
use App\Models\CrmLead;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmMapaTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano CRM '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_crm_leads'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant CRM '.uniqid(), 'slug' => 'tenant-crm-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_page_loads_and_widget_returns_only_geolocated_leads(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead Com Mapa', 'stage' => CrmLead::STAGE_NOVO, 'latitude' => -22.9, 'longitude' => -47.06]);
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead Sem Mapa', 'stage' => CrmLead::STAGE_NOVO]);

        $this->actingAs($admin);

        $this->get(CrmMapa::getUrl())->assertOk();

        $leads = Livewire::test(CrmLeadMapWidget::class)->instance()->getLeads();

        $this->assertCount(1, $leads);
        $this->assertSame('Lead Com Mapa', $leads[0]['name']);
    }
}
