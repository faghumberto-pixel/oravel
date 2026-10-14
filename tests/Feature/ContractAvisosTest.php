<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Avisos de contrato (14/10/2026): contratos de servico gerados por proposta
 * aceita (nascem em Rascunho) e contrato Ativo com a vigencia vencida.
 */
class ContractAvisosTest extends TestCase
{
    use DatabaseTransactions;

    private function setUpTenant(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Avisos '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_contracts'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Avisos '.uniqid(), 'slug' => 'tenant-avisos-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $user = User::create([
            'name' => 'Admin', 'email' => 'av-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $role = new Role(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $role->save();
        $user->assignRole($role);
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Aviso']);

        return [$tenant, $user, $client];
    }

    private function contrato(Tenant $tenant, Client $client, ?string $fim): Contract
    {
        return Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'contract_number' => 'CT-'.uniqid(),
            'service_category' => PropostaComercialItem::TYPE_MAO_DE_OBRA,
            'start_date' => now()->subMonths(3)->toDateString(), 'end_date' => $fim,
            'price' => 1000, 'billing_type' => Contract::BILLING_MENSAL_FIXO, 'status' => 'Ativo',
        ]);
    }

    public function test_expired_active_contract_is_warned_on_day_one_and_every_seven_days(): void
    {
        [$tenant, $user, $client] = $this->setUpTenant();
        $this->contrato($tenant, $client, now()->subDay()->toDateString());

        Artisan::call('contracts:check-expired');

        $this->assertSame(1, $user->notifications()->count());

        $this->travel(2)->days();
        Artisan::call('contracts:check-expired');
        $this->assertSame(1, $user->notifications()->count(), 'dia 3 de atraso: nao repete');

        $this->travelBack();
        $this->travel(7)->days(); // dia 8 de atraso
        Artisan::call('contracts:check-expired');
        $this->assertSame(2, $user->notifications()->count());
    }

    public function test_contracts_not_expired_or_not_active_are_not_warned(): void
    {
        [$tenant, $user, $client] = $this->setUpTenant();
        $this->contrato($tenant, $client, now()->addDays(10)->toDateString());
        $this->contrato($tenant, $client, null);
        $encerrado = $this->contrato($tenant, $client, now()->subDay()->toDateString());
        $encerrado->update(['status' => 'Encerrado']);

        Artisan::call('contracts:check-expired');

        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_service_contracts_from_accepted_proposal_notify_the_commercial_team(): void
    {
        [$tenant, $user, $client] = $this->setUpTenant();
        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $user->id,
            'status' => PropostaComercial::STATUS_APROVADA_INTERNA,
        ]);
        PropostaComercialItem::create([
            'tenant_id' => $tenant->id, 'proposta_comercial_id' => $proposta->id,
            'type' => PropostaComercialItem::TYPE_MAO_DE_OBRA, 'description' => 'Operador',
            'quantity' => 1, 'unit_price' => 8000,
            'start_date' => now()->startOfMonth(), 'end_date' => now()->startOfMonth()->addMonths(3),
        ]);

        $criados = $proposta->fresh()->gerarContratosDeServicos();

        $this->assertCount(1, $criados);
        $this->assertSame(1, $user->notifications()->count());
        $this->assertStringContainsString($criados[0]->contract_number, (string) $user->notifications()->first()->data['body']);

        // Idempotente: rodar de novo nao cria nem avisa outra vez.
        $proposta->fresh()->gerarContratosDeServicos();
        $this->assertSame(1, $user->notifications()->count());
    }
}
