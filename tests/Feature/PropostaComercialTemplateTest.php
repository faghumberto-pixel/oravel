<?php

namespace Tests\Feature;

use App\Filament\Resources\PropostaComercialTemplateResource\Pages\CreatePropostaComercialTemplate;
use App\Filament\Resources\PropostaComercialTemplateResource\Pages\EditPropostaComercialTemplate;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialTemplate;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * CRUD de PropostaComercialTemplate (cada tenant define seus próprios
 * termos padrão) + a garantia de que fillFromTemplate() copia por valor,
 * não referencia o template.
 */
class PropostaComercialTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Template Proposta '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_proposta_comercial'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Template Proposta', 'slug' => 'tenant-template-proposta-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    public function test_admin_do_tenant_cria_template_via_filament(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test(CreatePropostaComercialTemplate::class)
            ->fillForm([
                'name' => 'Termos Padrão de Locação',
                'is_default' => true,
                'is_active' => true,
                'default_valid_days' => 15,
                'default_terms' => 'Pagamento em até 30 dias após a entrega.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = PropostaComercialTemplate::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('Termos Padrão de Locação', $template->name);
        $this->assertTrue($template->is_default);
        $this->assertSame(15, $template->default_valid_days);
    }

    public function test_edicao_do_template_persiste(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $template = PropostaComercialTemplate::create([
            'tenant_id' => $tenant->id, 'name' => 'Template Original',
            'default_terms' => 'Texto original.',
        ]);

        Livewire::test(EditPropostaComercialTemplate::class, ['record' => $template->id])
            ->fillForm(['default_terms' => 'Texto atualizado.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Texto atualizado.', $template->fresh()->default_terms);
    }

    public function test_editar_template_depois_nao_retroage_em_proposta_ja_criada(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $template = PropostaComercialTemplate::create([
            'tenant_id' => $tenant->id, 'name' => 'Padrão', 'is_default' => true,
            'is_active' => true, 'default_terms' => 'Termo original.',
        ]);

        $proposta = PropostaComercial::create(['tenant_id' => $tenant->id, 'seller_user_id' => $admin->id]);
        $proposta->fillFromTemplate();
        $proposta->save();

        $this->assertSame('Termo original.', $proposta->fresh()->terms);

        Livewire::test(EditPropostaComercialTemplate::class, ['record' => $template->id])
            ->fillForm(['default_terms' => 'Termo alterado pelo admin depois.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Termo original.', $proposta->fresh()->terms, 'Editar o template não pode alterar proposta já criada.');
    }
}
