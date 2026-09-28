<?php

namespace Tests\Feature;

use App\Filament\Resources\PropostaComercialResource\Pages\ViewPropostaComercial;
use App\Filament\Resources\PropostaComercialResource\RelationManagers\InteractionsRelationManager;
use App\Models\Client;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialInteraction;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pedido explícito do usuário 28/09/2026: "criar laços que unam o
 * histórico das ações com o tempo e as providências que precisam ser
 * tomadas" -- mesmo padrão de CrmLeadInteraction/CrmLeadResourceTest.
 */
class PropostaComercialInteractionTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Interação Proposta '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_proposta_comercial'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Interação Proposta '.uniqid(), 'slug' => 'tenant-interacao-proposta-'.uniqid(),
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

    public function test_registering_an_interaction_stamps_user_and_current_status(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Interação']);
        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
            'status' => PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL,
        ]);

        $this->actingAs($admin);

        Livewire::test(InteractionsRelationManager::class, [
            'ownerRecord' => $proposta,
            'pageClass' => ViewPropostaComercial::class,
        ])
            ->callTableAction('create', null, data: [
                'contact_date' => now()->toDateTimeString(),
                'channel' => PropostaComercialInteraction::CHANNEL_TELEFONE,
                'summary' => 'Cliente pediu mais 2 dias pra decidir.',
                'next_action' => 'Ligar de novo quinta-feira.',
                'next_followup_date' => now()->addDays(3)->toDateString(),
            ]);

        $interaction = PropostaComercialInteraction::where('proposta_comercial_id', $proposta->id)->first();
        $this->assertNotNull($interaction);
        $this->assertSame($admin->id, $interaction->user_id);
        $this->assertSame(PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL, $interaction->status_at_time);
        $this->assertSame('Ligar de novo quinta-feira.', $interaction->next_action);
    }

    public function test_interactions_are_tenant_scoped(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$otherTenant, $otherAdmin] = $this->makeTenantAdmin();

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Escopo']);
        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
        ]);
        PropostaComercialInteraction::create([
            'tenant_id' => $tenant->id, 'proposta_comercial_id' => $proposta->id, 'user_id' => $admin->id,
            'channel' => PropostaComercialInteraction::CHANNEL_EMAIL, 'contact_date' => now(),
            'summary' => 'Contato do tenant certo', 'status_at_time' => PropostaComercial::STATUS_RASCUNHO,
        ]);

        $this->actingAs($otherAdmin);
        $this->assertSame(0, PropostaComercialInteraction::count());
    }

    public function test_only_author_or_admin_can_delete_an_interaction(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $author = User::create([
            'name' => 'Vendedor Autor', 'email' => 'autor-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $author->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();

        $outroVendedor = User::create([
            'name' => 'Outro Vendedor', 'email' => 'outro-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $outroVendedor->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Autor']);
        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $author->id,
        ]);
        $interaction = PropostaComercialInteraction::create([
            'tenant_id' => $tenant->id, 'proposta_comercial_id' => $proposta->id, 'user_id' => $author->id,
            'channel' => PropostaComercialInteraction::CHANNEL_EMAIL, 'contact_date' => now(),
            'summary' => 'Registro do autor', 'status_at_time' => PropostaComercial::STATUS_RASCUNHO,
        ]);

        $this->assertFalse($outroVendedor->can('delete', $interaction));
        $this->assertTrue($author->can('delete', $interaction));
        $this->assertTrue($admin->can('delete', $interaction));
    }
}
