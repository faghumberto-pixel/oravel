<?php

namespace Tests\Feature;

use App\Filament\Resources\ContractResource;
use App\Filament\Resources\ContractResource\Pages\ListContracts;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Lista de Contratos em cartoes coloridos com cards de totais clicaveis (pedido do usuario 2026-11-03). */
class ContractCartoesTest extends TestCase
{
    use DatabaseTransactions;

    private function cenario(): array
    {
        $plan = Plan::create([
            'name' => 'Plano CtrCartoes '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_contracts', 'tabela_clients', 'tabela_assets'],
        ]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'ctr-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'adm-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return [$tenant, Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Cartao']), Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Cartao', 'tag' => 'AC-1', 'status' => 'disponivel'])];
    }

    private function contrato(Tenant $t, Client $c, Asset $a, string $numero, array $o = []): Contract
    {
        return Contract::create($o + [
            'tenant_id' => $t->id, 'client_id' => $c->id, 'asset_id' => $a->id,
            'contract_number' => $numero, 'start_date' => now()->subMonths(2), 'price' => 100, 'status' => 'Ativo',
        ]);
    }

    public function test_cor_do_contrato_por_significado(): void
    {
        [$t, $c, $a] = $this->cenario();

        $this->assertSame('emerald', ContractResource::corDoContrato($this->contrato($t, $c, $a, 'C-1', ['end_date' => now()->addMonths(6)])));
        $this->assertSame('amber', ContractResource::corDoContrato($this->contrato($t, $c, $a, 'C-2', ['end_date' => now()->addDays(10)])));
        $this->assertSame('red', ContractResource::corDoContrato($this->contrato($t, $c, $a, 'C-3', ['end_date' => now()->subDays(3)])));
        $this->assertSame('purple', ContractResource::corDoContrato($this->contrato($t, $c, $a, 'C-4', ['status' => 'Encerrado'])));
        $this->assertSame('cyan', ContractResource::corDoContrato($this->contrato($t, $c, $a, 'C-5', ['status' => 'Draft'])));
    }

    public function test_lista_em_cartoes_e_cards_filtram_por_situacao_vigencia_e_assinatura(): void
    {
        [$t, $c, $a] = $this->cenario();
        $ok = $this->contrato($t, $c, $a, 'CT-OK', ['end_date' => now()->addMonths(6)]);
        $perto = $this->contrato($t, $c, $a, 'CT-PERTO', ['end_date' => now()->addDays(10)]);
        $vencido = $this->contrato($t, $c, $a, 'CT-VENCIDO', ['end_date' => now()->subDays(3)]);
        $rascunho = $this->contrato($t, $c, $a, 'CT-RASC', ['status' => 'Draft']);

        $lista = Livewire::test(ListContracts::class)
            ->assertSee('CT-OK')->assertSeeHtml('fi-ta-content-grid')
            ->assertSeeHtml('border-emerald-600')->assertSeeHtml('border-amber-600')->assertSeeHtml('border-red-600');

        $cards = collect($lista->instance()->cards())->keyBy('chave');
        $this->assertSame(4, $cards['total']['total']);
        $this->assertSame(3, $cards['ativo']['total']);
        $this->assertSame(1, $cards['rascunho']['total']);
        $this->assertSame(1, $cards['vence30']['total']);
        $this->assertSame(1, $cards['vencida']['total']);
        $this->assertSame(4, $cards['pendentes']['total']);
        $this->assertSame(0, $cards['assinados']['total']);

        $lista->call('filtrarCard', 'vence30')
            ->assertCanSeeTableRecords([$perto])->assertCanNotSeeTableRecords([$ok, $vencido, $rascunho])
            ->assertSet('tableFilters.vence_em_30_dias.isActive', true);
        $this->assertTrue(collect($lista->instance()->cards())->firstWhere('chave', 'vence30')['ativo']);

        $lista->call('filtrarCard', 'rascunho')
            ->assertCanSeeTableRecords([$rascunho])->assertCanNotSeeTableRecords([$ok, $perto, $vencido])
            ->call('filtrarCard', 'total')
            ->assertCanSeeTableRecords([$ok, $perto, $vencido, $rascunho]);
    }
}
