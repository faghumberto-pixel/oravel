<?php

namespace Tests\Feature;

use App\Livewire\ContagemEstoqueMobile;
use App\Models\InternalUnit;
use App\Models\Material;
use App\Models\Plan;
use App\Models\Role;
use App\Models\StockCount;
use App\Models\StockLocation;
use App\Models\StockPlacement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Estoque\ContagemFisica;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** App de contagem física no celular: lista, endereços, contagem cega, histórico de quem contou, e bloqueio sem a chave do contrato. */
class ContagemEstoqueMobileTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $admin;

    private StockCount $contagem;

    private function empresa(array $features = ['tabela_materials' => true, 'tabela_stock_structure' => true]): void
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $this->tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'cm-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $this->admin = User::create(['name' => 'Joana Contadora', 'email' => 'j-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        $this->admin->forceFill(['email_verified_at' => now()])->save();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]));
        $this->actingAs($this->admin);
    }

    private function contagemComDoisItens(): void
    {
        $unidade = InternalUnit::unguarded(fn () => InternalUnit::create(['tenant_id' => $this->tenant->id, 'name' => 'Matriz', 'code' => 'MTZ', 'type' => 'matriz', 'is_active' => true]));
        $end = StockLocation::create(['tenant_id' => $this->tenant->id, 'internal_unit_id' => $unidade->id, 'aisle' => 'A', 'shelf' => '01']);
        foreach (['Papel A4' => 20, 'Caneta azul' => 40] as $nome => $qtd) {
            $m = Material::unguarded(fn () => Material::create(['tenant_id' => $this->tenant->id, 'sku' => 'M-'.uniqid(), 'name' => $nome, 'unit_cost' => 1, 'min_stock' => 1, 'max_stock' => 99, 'current_stock' => $qtd]));
            StockPlacement::create(['tenant_id' => $this->tenant->id, 'stock_location_id' => $end->id, 'item_type' => 'material', 'item_id' => (string) $m->id, 'quantity' => $qtd]);
        }
        $this->contagem = app(ContagemFisica::class)->criar((string) $this->tenant->id, (string) $unidade->id, 'Inventário de novembro', null, null, (string) $this->admin->id);
    }

    public function test_fluxo_completo_contagem_cega_com_historico(): void
    {
        $this->empresa();
        $this->contagemComDoisItens();
        $endereco = (string) $this->contagem->items()->value('stock_location_id');

        $tela = Livewire::test(ContagemEstoqueMobile::class)
            ->assertSee('Inventário de novembro')
            ->assertSee('0 de 2 itens contados')
            ->call('abrirContagem', $this->contagem->id)
            ->assertSee('C-A · P-01')
            ->call('abrirEndereco', $endereco)
            ->assertSee('Papel A4')
            ->assertSee('Caneta azul')
            ->assertDontSee('20,000'); // cega: o saldo do sistema nao aparece para quem conta

        $papel = $this->contagem->items()->where('item_name', 'Papel A4')->first();
        $tela->set('quantidades.'.$papel->id, '18,5')->call('registrar', $papel->id);
        $tela->set('quantidades.'.$papel->id, '19')->call('registrar', $papel->id); // recontagem

        $papel = $papel->fresh();
        $this->assertEquals(19, $papel->counted_quantity);
        $this->assertSame('Joana Contadora', $papel->counted_by_name);
        $this->assertCount(2, $papel->entries);
        $this->assertEquals(['celular', 'celular'], $papel->entries->pluck('source')->all());
        $this->assertEquals([19.0, 18.5], $papel->entries->pluck('quantity')->map(fn ($q) => (float) $q)->all());

        $tela->assertSee('Contado por Joana Contadora');
    }

    public function test_quantidade_invalida_nao_registra_e_contagem_finalizada_nao_aceita(): void
    {
        $this->empresa();
        $this->contagemComDoisItens();
        $item = $this->contagem->items()->first();

        Livewire::test(ContagemEstoqueMobile::class, ['contagem' => $this->contagem->id])
            ->call('abrirEndereco', (string) $item->stock_location_id)
            ->set('quantidades.'.$item->id, 'abc')->call('registrar', $item->id)
            ->assertSee('Digite a quantidade contada');
        $this->assertNull($item->fresh()->counted_quantity);

        $this->contagem->update(['status' => StockCount::FINALIZADA]);
        Livewire::test(ContagemEstoqueMobile::class, ['contagem' => $this->contagem->id])
            ->call('abrirEndereco', (string) $item->stock_location_id)
            ->set('quantidades.'.$item->id, '5')->call('registrar', $item->id)
            ->assertSee('já foi finalizada');
        $this->assertNull($item->fresh()->counted_quantity);
    }

    public function test_sem_a_chave_do_contrato_o_app_nao_abre(): void
    {
        $this->empresa(['tabela_materials' => true]);

        $this->get('/admin/contagem-estoque')->assertForbidden();
    }
}
