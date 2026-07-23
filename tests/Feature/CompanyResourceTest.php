<?php

namespace Tests\Feature;

use App\Filament\Resources\CompanyResource\Pages\CreateCompany;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * CompanyResource era scaffolding nunca terminada: form()/table() vazios,
 * $shouldRegisterNavigation=false, e Company::$fillable só tinha 'name'
 * mesmo a tabela tendo address/city/state/phone reais (mass-assignment
 * descartava tudo silenciosamente, mesma classe de bug já documentada em
 * InternalUnit/Client). Corrigido ao popular dados de demo.
 */
class CompanyResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Company '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_companies'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Company '.uniqid(), 'slug' => 'tenant-company-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_create_form_persists_address_city_state_and_phone(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test(CreateCompany::class)
            ->fillForm([
                'name' => 'Empresa Teste Ltda',
                'address' => 'Rua das Flores, 100',
                'city' => 'Campinas',
                'state' => 'SP',
                'phone' => '(19) 3333-4444',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $company = Company::where('name', 'Empresa Teste Ltda')->sole();
        $this->assertSame('Rua das Flores, 100', $company->address);
        $this->assertSame('Campinas', $company->city);
        $this->assertSame('SP', $company->state);
        $this->assertSame('(19) 3333-4444', $company->phone);
        $this->assertSame($tenant->id, $company->tenant_id);
    }
}
