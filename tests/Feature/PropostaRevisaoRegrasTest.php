<?php

namespace Tests\Feature;

use App\Filament\Resources\PropostaComercialResource\Pages\ViewPropostaComercial;
use App\Models\Client;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regras da revisao da proposta (14/10/2026): o vendedor nao revisa a propria
 * proposta e proposta rejeitada/recusada pode ser reaberta pela tela.
 */
class PropostaRevisaoRegrasTest extends TestCase
{
    use DatabaseTransactions;

    private function setUpTenant(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Rev '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_proposta_comercial'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Rev '.uniqid(), 'slug' => 'tenant-rev-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $vendedor = User::create([
            'name' => 'Vendedor', 'email' => 'v-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'a-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $role = new Role(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $role->save();
        $admin->assignRole($role);
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Rev', 'email' => 'c@x.com']);

        return [$tenant, $vendedor, $admin, $client];
    }

    private function enviada(Tenant $tenant, User $vendedor, Client $client): PropostaComercial
    {
        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $vendedor->id,
            'status' => PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL,
        ]);
        PropostaComercialItem::create([
            'tenant_id' => $tenant->id, 'proposta_comercial_id' => $proposta->id,
            'type' => PropostaComercialItem::TYPE_SERVICO, 'description' => 'Servico',
            'quantity' => 1, 'unit_price' => 100,
        ]);

        return $proposta->fresh();
    }

    public function test_seller_cannot_approve_or_reject_own_proposal(): void
    {
        [$tenant, $vendedor, $admin, $client] = $this->setUpTenant();
        $proposta = $this->enviada($tenant, $vendedor, $client);

        foreach ([
            fn () => $proposta->aprovar($vendedor),
            fn () => $proposta->rejeitar($vendedor, 'Motivo'),
        ] as $tentativa) {
            try {
                $tentativa();
                $this->fail('O vendedor revisou a própria proposta.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('própria proposta', $e->getMessage());
            }
        }

        $this->assertSame(PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL, $proposta->fresh()->status);
    }

    public function test_another_reviewer_can_reject(): void
    {
        [$tenant, $vendedor, $admin, $client] = $this->setUpTenant();
        $proposta = $this->enviada($tenant, $vendedor, $client);

        $proposta->rejeitar($admin, 'Preço fora do praticado.');

        $this->assertSame(PropostaComercial::STATUS_REJEITADA, $proposta->fresh()->status);
    }

    public function test_tenant_admin_may_review_own_proposal(): void
    {
        [$tenant, $vendedor, $admin, $client] = $this->setUpTenant();
        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
            'status' => PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL,
        ]);

        $proposta->rejeitar($admin, 'Teste');

        $this->assertSame(PropostaComercial::STATUS_REJEITADA, $proposta->fresh()->status);
    }

    public function test_rejected_proposal_can_be_reopened_from_the_screen(): void
    {
        [$tenant, $vendedor, $admin, $client] = $this->setUpTenant();
        $proposta = $this->enviada($tenant, $vendedor, $client);
        $proposta->rejeitar($admin, 'Ajustar valor.');

        $this->actingAs($admin);
        Livewire::test(ViewPropostaComercial::class, ['record' => $proposta->getKey()])
            ->assertActionVisible('reabrir')
            ->callAction('reabrir');

        $fresh = $proposta->fresh();
        $this->assertSame(PropostaComercial::STATUS_RASCUNHO, $fresh->status);
        $this->assertNull($fresh->rejection_reason);
    }

    public function test_reopen_action_is_hidden_for_a_draft(): void
    {
        [$tenant, $vendedor, $admin, $client] = $this->setUpTenant();
        $proposta = PropostaComercial::create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $vendedor->id]);

        $this->actingAs($admin);
        Livewire::test(ViewPropostaComercial::class, ['record' => $proposta->getKey()])
            ->assertActionHidden('reabrir');
    }
}
