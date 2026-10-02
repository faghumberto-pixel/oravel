<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmLeadResource\Pages\EditCrmLead;
use App\Models\CrmLead;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CrmLeadAdOriginTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tela_do_lead_mostra_a_origem_do_anuncio_e_salvar_nao_apaga(): void
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_crm_leads']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'adm-'.uniqid().'@t.com', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($admin);

        $lead = CrmLead::create([
            'tenant_id' => $tenant->id, 'name' => 'Fulano', 'company_name' => 'Locadora X', 'stage' => CrmLead::STAGE_NOVO,
            'gclid' => 'ABC-123', 'utm_source' => 'google', 'utm_campaign' => 'oravel-search', 'landing_url' => 'https://oravel.com.br/locadoras/',
        ]);

        Livewire::test(EditCrmLead::class, ['record' => $lead->getKey()])
            ->assertFormSet(['gclid' => 'ABC-123', 'utm_campaign' => 'oravel-search', 'landing_url' => 'https://oravel.com.br/locadoras/'])
            ->set('data.name', 'Fulano Editado')
            ->call('save')
            ->assertHasNoFormErrors();

        $lead->refresh();
        $this->assertSame('Fulano Editado', $lead->name);
        $this->assertSame('ABC-123', $lead->gclid);
        $this->assertSame('oravel-search', $lead->utm_campaign);
    }
}
