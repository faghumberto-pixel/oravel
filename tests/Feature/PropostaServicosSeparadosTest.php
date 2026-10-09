<?php

namespace Tests\Feature;

use App\Filament\Resources\ContractResource\Pages\ListContracts;
use App\Models\AccountReceivable;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Venda separada da locação: mão de obra, segurança e documentação,
 * acessórios e insumos viram contratos próprios quando o cliente aceita a
 * proposta (PropostaComercial::gerarContratosDeServicos()).
 */
class PropostaServicosSeparadosTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Serv '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_contracts', 'tabela_account_receivables', 'tabela_rental_overage_charges', 'tabela_contract_measurements'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Serv '.uniqid(), 'slug' => 'tenant-serv-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $user = User::create([
            'name' => 'Admin', 'email' => 'a-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $user];
    }

    private function makeProposta(Tenant $tenant, User $user, array $items): PropostaComercial
    {
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente '.uniqid(), 'email' => 'c@x.com']);
        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $user->id,
            'status' => PropostaComercial::STATUS_APROVADA_INTERNA,
        ]);

        foreach ($items as [$type, $desc, $qty, $price]) {
            PropostaComercialItem::create([
                'tenant_id' => $tenant->id, 'proposta_comercial_id' => $proposta->id,
                'type' => $type, 'description' => $desc, 'quantity' => $qty, 'unit_price' => $price,
                'start_date' => now()->startOfMonth(), 'end_date' => now()->startOfMonth()->addMonths(3),
            ]);
        }

        return $proposta->fresh();
    }

    public function test_accepting_creates_one_draft_contract_per_service_category_without_asset(): void
    {
        [$tenant, $user] = $this->makeTenantAdmin();
        $proposta = $this->makeProposta($tenant, $user, [
            [PropostaComercialItem::TYPE_MAO_DE_OBRA, 'Operador', 1, 8000],
            [PropostaComercialItem::TYPE_MAO_DE_OBRA, 'Encarregado', 1, 2000],
            [PropostaComercialItem::TYPE_INSUMO, 'Óleo', 10, 50],
        ]);

        $proposta->aceitarPeloCliente();

        $contratos = Contract::where('proposta_comercial_id', $proposta->id)->get()->keyBy('service_category');
        $this->assertCount(2, $contratos);

        $mo = $contratos[PropostaComercialItem::TYPE_MAO_DE_OBRA];
        $this->assertEqualsWithDelta(10000.0, (float) $mo->price, 0.01);
        $this->assertSame('Draft', $mo->status);
        $this->assertNull($mo->asset_id);
        $this->assertSame($proposta->client_id, $mo->client_id);
        $this->assertEqualsWithDelta(500.0, (float) $contratos[PropostaComercialItem::TYPE_INSUMO]->price, 0.01);
    }

    public function test_proposal_with_only_services_has_no_rental_request_but_still_gets_contracts(): void
    {
        [$tenant, $user] = $this->makeTenantAdmin();
        $proposta = $this->makeProposta($tenant, $user, [
            [PropostaComercialItem::TYPE_SEGURANCA_DOCUMENTACAO, 'ART e PGR', 1, 3000],
        ]);

        $proposta->aceitarPeloCliente();

        $this->assertNull($proposta->fresh()->solicitacao_locacao_id);
        $this->assertSame(1, Contract::where('proposta_comercial_id', $proposta->id)->count());
    }

    public function test_rental_only_proposal_creates_no_service_contract_and_generation_is_idempotent(): void
    {
        [$tenant, $user] = $this->makeTenantAdmin();
        $locacao = $this->makeProposta($tenant, $user, [
            [PropostaComercialItem::TYPE_SERVICO, 'Outro serviço', 1, 100],
        ]);
        $this->assertSame([], $locacao->gerarContratosDeServicos());

        $proposta = $this->makeProposta($tenant, $user, [
            [PropostaComercialItem::TYPE_ACESSORIO, 'Bandeja', 2, 150],
        ]);
        $this->assertCount(1, $proposta->gerarContratosDeServicos());
        $this->assertCount(0, $proposta->gerarContratosDeServicos());
        $this->assertSame(1, Contract::where('proposta_comercial_id', $proposta->id)->count());
    }

    public function test_service_contract_without_asset_can_be_invoiced(): void
    {
        [$tenant, $user] = $this->makeTenantAdmin();
        $proposta = $this->makeProposta($tenant, $user, [
            [PropostaComercialItem::TYPE_MAO_DE_OBRA, 'Operador', 1, 6000],
        ]);
        $proposta->gerarContratosDeServicos();
        $contrato = Contract::where('proposta_comercial_id', $proposta->id)->sole();
        $contrato->update(['status' => 'Ativo', 'start_date' => now()->subMonths(2)]);
        $this->actingAs($user);

        Livewire::test(ListContracts::class)->callTableAction('faturar', $contrato, [
            'periodo_inicio' => now()->subMonth()->startOfMonth()->toDateString(),
            'periodo_fim' => now()->subMonth()->endOfMonth()->toDateString(),
            'vencimento' => now()->addDays(10)->toDateString(),
        ]);

        $this->assertEqualsWithDelta(6000.0, (float) AccountReceivable::where('contract_id', $contrato->id)->sole()->amount, 0.01);
    }
}
