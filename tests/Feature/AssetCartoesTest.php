<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\ListAssets;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Lista de Ativos em cartoes coloridos, com cards de totais clicaveis (pedido do usuario 2026-11-03). */
class AssetCartoesTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Cartoes '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets'],
        ]);
        $tenant = Tenant::create(['name' => 'Tenant Cartoes '.uniqid(), 'slug' => 'cartoes-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'adm-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function ativo(Tenant $t, string $nome, string $status, array $o = []): Asset
    {
        return Asset::create($o + ['tenant_id' => $t->id, 'name' => $nome, 'tag' => 'T-'.uniqid(), 'status' => $status]);
    }

    public function test_lista_usa_cartoes_coloridos_por_status(): void
    {
        [$t, $admin] = $this->admin();
        $this->ativo($t, 'Gerador Verde', Asset::STATUS_DISPONIVEL);
        $this->ativo($t, 'Munck Vermelho', Asset::STATUS_MANUTENCAO);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListAssets::class)
            ->assertSee('Gerador Verde')->assertSee('Munck Vermelho')
            ->assertSeeHtml('fi-ta-content-grid')
            ->assertSeeHtml('border-emerald-600')->assertSeeHtml('border-red-600');
    }

    public function test_cards_contam_por_status_e_clique_filtra_a_lista(): void
    {
        [$t, $admin] = $this->admin();
        $a = $this->ativo($t, 'Alfa', Asset::STATUS_DISPONIVEL);
        $b = $this->ativo($t, 'Beta', Asset::STATUS_LOCADO);
        $c = $this->ativo($t, 'Gama', Asset::STATUS_LOCADO, ['grupo' => Asset::GRUPO_VEICULO]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $lista = Livewire::test(ListAssets::class);
        $cards = collect($lista->instance()->cards())->keyBy('chave');
        $this->assertSame(3, $cards['total']['total']);
        $this->assertSame(1, $cards['disponivel']['total']);
        $this->assertSame(2, $cards['locado']['total']);
        $this->assertSame(1, $cards['veiculos']['total']);

        $lista->call('filtrarCard', 'locado')
            ->assertCanSeeTableRecords([$b, $c])->assertCanNotSeeTableRecords([$a])
            ->assertSet('tableFilters.status.value', Asset::STATUS_LOCADO);

        // Com "Locado" ativo, os outros cards continuam mostrando o proprio total (nao zeram).
        $cards = collect($lista->instance()->cards())->keyBy('chave');
        $this->assertTrue($cards['locado']['ativo']);
        $this->assertSame(1, $cards['disponivel']['total']);

        // Busca reflete nos cards.
        $lista->set('tableSearch', 'Alfa');
        $cards = collect($lista->instance()->cards())->keyBy('chave');
        $this->assertSame(1, $cards['total']['total']);
        $this->assertSame(0, $cards['locado']['total']);

        $lista->set('tableSearch', '')->call('filtrarCard', 'total')
            ->assertCanSeeTableRecords([$a, $b, $c]);
    }
}
