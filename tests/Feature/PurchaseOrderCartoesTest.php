<?php

namespace Tests\Feature;

use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\PurchaseOrderResource\Pages\ListPurchaseOrders;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Pedidos de Compra em cartoes (padrao CRM Comercial), sem cards no topo, com cards de totais (pedido 2026-11-03). */
class PurchaseOrderCartoesTest extends TestCase
{
    use DatabaseTransactions;

    private function cenario(): array
    {
        $plan = Plan::create([
            'name' => 'Plano PO '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_purchase_orders', 'tabela_suppliers'],
        ]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'po-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'adm-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return [$tenant, Supplier::create(['tenant_id' => $tenant->id, 'name' => 'Fornecedor PO'])];
    }

    private function ordem(Tenant $t, Supplier $s, array $o = []): PurchaseOrder
    {
        return PurchaseOrder::create($o + ['tenant_id' => $t->id, 'supplier_id' => $s->id, 'status' => PurchaseOrder::STATUS_ABERTA, 'total_value' => 100, 'created_by_user_id' => auth()->id()]);
    }

    public function test_cor_da_ordem_por_significado(): void
    {
        [$t, $s] = $this->cenario();

        $this->assertSame('blue', PurchaseOrderResource::corDaOrdem($this->ordem($t, $s, ['expected_delivery_date' => now()->addDays(5)])));
        $this->assertSame('red', PurchaseOrderResource::corDaOrdem($this->ordem($t, $s, ['expected_delivery_date' => now()->subDays(2)])));
        $this->assertSame('amber', PurchaseOrderResource::corDaOrdem($this->ordem($t, $s, ['status' => PurchaseOrder::STATUS_PARCIALMENTE_RECEBIDA])));
        $this->assertSame('emerald', PurchaseOrderResource::corDaOrdem($this->ordem($t, $s, ['status' => PurchaseOrder::STATUS_RECEBIDA, 'expected_delivery_date' => now()->subDays(9)])));
        $this->assertSame('gray', PurchaseOrderResource::corDaOrdem($this->ordem($t, $s, ['status' => PurchaseOrder::STATUS_CANCELADA])));
    }

    public function test_lista_em_cartoes_sem_cards_antigos_e_cards_filtram(): void
    {
        [$t, $s] = $this->cenario();
        $aberta = $this->ordem($t, $s, ['expected_delivery_date' => now()->addDays(5)]);
        $atrasada = $this->ordem($t, $s, ['expected_delivery_date' => now()->subDays(3)]);
        $recebida = $this->ordem($t, $s, ['status' => PurchaseOrder::STATUS_RECEBIDA]);

        $this->assertSame([], (new \ReflectionMethod(ListPurchaseOrders::class, 'getHeaderWidgets'))->invoke(new ListPurchaseOrders));

        $lista = Livewire::test(ListPurchaseOrders::class)
            ->assertSeeHtml('fi-ta-content-grid')->assertSeeHtml('border-red-600')->assertSeeHtml('border-emerald-600')->assertSee('Fornecedor PO');

        $cards = collect($lista->instance()->cards())->keyBy('chave');
        $this->assertSame(3, $cards['total']['total']);
        $this->assertSame(2, $cards['aberta']['total']);
        $this->assertSame(1, $cards['recebida']['total']);
        $this->assertSame(1, $cards['atrasada']['total']);

        $lista->call('filtrarCard', 'atrasada')
            ->assertCanSeeTableRecords([$atrasada])->assertCanNotSeeTableRecords([$aberta, $recebida])
            ->call('filtrarCard', 'recebida')
            ->assertCanSeeTableRecords([$recebida])->assertCanNotSeeTableRecords([$aberta, $atrasada])
            ->call('filtrarCard', 'total')
            ->assertCanSeeTableRecords([$aberta, $atrasada, $recebida]);
    }
}
