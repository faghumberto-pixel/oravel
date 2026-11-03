<?php

namespace Tests\Feature;

use App\Filament\Resources\PropostaComercialResource\Pages\ListPropostaComerciais;
use App\Filament\Resources\QuoteResource\Pages\ListQuotes;
use App\Models\Client;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Orcamentos e Propostas em cartoes (padrao CRM Comercial), sem cards/graficos antigos no topo (pedido 2026-11-03). */
class OrcamentoPropostaCartoesTest extends TestCase
{
    use DatabaseTransactions;

    private function cenario(): array
    {
        $plan = Plan::create([
            'name' => 'Plano OP '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_quotes', 'tabela_proposta_comercial', 'tabela_clients'],
        ]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'op-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'adm-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return [$tenant, $admin, Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Cartao'])];
    }

    public function test_orcamentos_em_cartoes_sem_cards_antigos_e_cards_filtram_por_status_e_tipo(): void
    {
        [$t, $admin, $c] = $this->cenario();
        $rasc = Quote::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'status' => Quote::STATUS_RASCUNHO, 'total_value' => 100]);
        $aprov = Quote::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'status' => Quote::STATUS_APROVADO, 'type' => Quote::TYPE_TERCEIRO, 'total_value' => 250]);
        $repr = Quote::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'status' => Quote::STATUS_REPROVADO, 'total_value' => 50]);

        $this->assertSame([], (new \ReflectionMethod(ListQuotes::class, 'getHeaderWidgets'))->invoke(new ListQuotes));

        $lista = Livewire::test(ListQuotes::class)
            ->assertSeeHtml('fi-ta-content-grid')->assertSeeHtml('border-emerald-600')->assertSeeHtml('border-red-600')->assertSee('Cliente Cartao');

        $cards = collect($lista->instance()->cards())->keyBy('chave');
        $this->assertSame(3, $cards['total']['total']);
        $this->assertSame(1, $cards['aprovado']['total']);
        $this->assertSame(1, $cards['tipo_terceiro']['total']);
        $this->assertSame(2, $cards['tipo_interno']['total']);

        $lista->call('filtrarCard', 'aprovado')
            ->assertCanSeeTableRecords([$aprov])->assertCanNotSeeTableRecords([$rasc, $repr])
            ->call('filtrarCard', 'tipo_terceiro')
            ->assertCanSeeTableRecords([$aprov])->assertCanNotSeeTableRecords([$rasc, $repr])
            ->call('filtrarCard', 'total')
            ->assertCanSeeTableRecords([$rasc, $aprov, $repr]);
    }

    public function test_propostas_em_cartoes_com_cards_por_status(): void
    {
        [$t, $admin, $c] = $this->cenario();
        $rasc = PropostaComercial::create(['tenant_id' => $t->id, 'seller_user_id' => $admin->id, 'client_id' => $c->id]);
        $aceita = PropostaComercial::create(['tenant_id' => $t->id, 'seller_user_id' => $admin->id, 'client_id' => $c->id, 'status' => PropostaComercial::STATUS_ACEITA_PELO_CLIENTE]);

        $lista = Livewire::test(ListPropostaComerciais::class)
            ->assertSeeHtml('fi-ta-content-grid')->assertSeeHtml('border-emerald-600')->assertSee('Cliente Cartao');

        $cards = collect($lista->instance()->cards())->keyBy('chave');
        $this->assertSame(2, $cards['total']['total']);
        $this->assertSame(1, $cards[PropostaComercial::STATUS_ACEITA_PELO_CLIENTE]['total']);

        $lista->call('filtrarCard', PropostaComercial::STATUS_ACEITA_PELO_CLIENTE)
            ->assertCanSeeTableRecords([$aceita])->assertCanNotSeeTableRecords([$rasc])
            ->assertSet('tableFilters.status.value', PropostaComercial::STATUS_ACEITA_PELO_CLIENTE)
            ->call('filtrarCard', 'total')
            ->assertCanSeeTableRecords([$rasc, $aceita]);
    }
}
