<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource\Pages\ListClients;
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

/** Lista de Clientes em cartoes (padrao do CRM Comercial) com cards de totais clicaveis (pedido do usuario 2026-11-03). */
class ClientCartoesTest extends TestCase
{
    use DatabaseTransactions;

    private function cenario(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano CliCartoes '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_clients', 'tabela_contracts', 'tabela_assets'],
        ]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'cli-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'adm-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $tenant;
    }

    public function test_lista_em_cartoes_com_cards_que_filtram_por_contrato_portal_e_nicho(): void
    {
        $t = $this->cenario();
        $asset = Asset::create(['tenant_id' => $t->id, 'name' => 'Ativo', 'tag' => 'A-1', 'status' => 'disponivel']);
        $com = Client::create(['tenant_id' => $t->id, 'name' => 'Cliente Com Contrato', 'activity_type' => Client::NICHE_CONSTRUCAO_CIVIL, 'portal_access_enabled_at' => now()]);
        $sem = Client::create(['tenant_id' => $t->id, 'name' => 'Cliente Sem Contrato', 'activity_type' => Client::NICHE_EVENTOS]);
        Contract::create(['tenant_id' => $t->id, 'client_id' => $com->id, 'asset_id' => $asset->id, 'contract_number' => 'CC-1', 'start_date' => now(), 'price' => 10, 'status' => 'Ativo']);

        $lista = Livewire::test(ListClients::class)
            ->assertSee('Cliente Com Contrato')->assertSee('1 contrato ativo')->assertSee('Sem contrato ativo')
            ->assertSeeHtml('fi-ta-content-grid')->assertSeeHtml('border-emerald-600');

        $cards = collect($lista->instance()->cards())->keyBy('chave');
        $this->assertSame(2, $cards['total']['total']);
        $this->assertSame(1, $cards['com_contrato']['total']);
        $this->assertSame(1, $cards['sem_contrato']['total']);
        $this->assertSame(1, $cards['portal']['total']);
        $this->assertSame(1, $cards['nicho_eventos']['total']);

        $lista->call('filtrarCard', 'sem_contrato')
            ->assertCanSeeTableRecords([$sem])->assertCanNotSeeTableRecords([$com])
            ->call('filtrarCard', 'nicho_construcao_civil')
            ->assertCanSeeTableRecords([$com])->assertCanNotSeeTableRecords([$sem])
            ->assertSet('tableFilters.activity_type.value', Client::NICHE_CONSTRUCAO_CIVIL)
            ->call('filtrarCard', 'total')
            ->assertCanSeeTableRecords([$com, $sem]);
    }

    public function test_lista_sem_widgets_no_topo(): void
    {
        $this->cenario();

        $this->assertSame([], (new \ReflectionMethod(ListClients::class, 'getHeaderWidgets'))->invoke(new ListClients));
    }
}
