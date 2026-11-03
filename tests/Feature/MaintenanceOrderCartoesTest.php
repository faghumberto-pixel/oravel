<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceOrderResource;
use App\Filament\Resources\MaintenanceOrderResource\Pages\ListMaintenanceOrders;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Lista de Ordens de Servico em cartoes (padrao CRM Comercial), sem graficos, com cards de totais (pedido 2026-11-03). */
class MaintenanceOrderCartoesTest extends TestCase
{
    use DatabaseTransactions;

    private function cenario(): array
    {
        $plan = Plan::create([
            'name' => 'Plano OS '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_maintenance_orders', 'tabela_assets'],
        ]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'os-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'adm-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return [$tenant, Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador OS', 'tag' => 'G-1', 'status' => 'disponivel'])];
    }

    private function os(Tenant $t, Asset $a, string $descricao, array $o = []): MaintenanceOrder
    {
        return MaintenanceOrder::create($o + [
            'tenant_id' => $t->id, 'asset_id' => $a->id, 'description' => $descricao,
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'status' => 'Aberto',
        ]);
    }

    public function test_cor_da_os_por_significado(): void
    {
        [$t, $a] = $this->cenario();
        $nova = $this->os($t, $a, 'nova');
        $velha = $this->os($t, $a, 'velha');
        $velha->forceFill(['created_at' => now()->subDays(5)])->saveQuietly();
        $concluida = $this->os($t, $a, 'ok', ['status' => 'Concluída']);

        $this->assertSame('blue', MaintenanceOrderResource::corDaOs($nova));
        $this->assertSame('red', MaintenanceOrderResource::corDaOs($velha->fresh()));
        $this->assertSame('emerald', MaintenanceOrderResource::corDaOs($concluida));
    }

    public function test_lista_em_cartoes_sem_graficos_com_cards_que_filtram(): void
    {
        [$t, $a] = $this->cenario();
        $aberta = $this->os($t, $a, 'aberta');
        $concluida = $this->os($t, $a, 'concluida', ['status' => 'Concluída', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE]);
        $atrasada = $this->os($t, $a, 'atrasada');
        $atrasada->forceFill(['created_at' => now()->subDays(6)])->saveQuietly();

        $lista = Livewire::test(ListMaintenanceOrders::class)
            ->assertSeeHtml('fi-ta-content-grid')->assertSeeHtml('border-red-600')->assertSeeHtml('border-emerald-600')
            ->assertDontSee('Evolução Mensal');

        $cards = collect($lista->instance()->cards())->keyBy('chave');
        $this->assertSame(3, $cards['total']['total']);
        $this->assertSame(2, $cards['aberto']['total']);
        $this->assertSame(1, $cards['concluida']['total']);
        $this->assertSame(1, $cards['atrasada']['total']);
        $this->assertSame(1, $cards['preventiva']['total']);

        $lista->call('filtrarCard', 'concluida')
            ->assertCanSeeTableRecords([$concluida])->assertCanNotSeeTableRecords([$aberta, $atrasada])
            ->assertSet('tableFilters.status.values', ['Concluída']);
        $this->assertTrue(collect($lista->instance()->cards())->firstWhere('chave', 'concluida')['ativo']);

        $lista->call('filtrarCard', 'atrasada')
            ->assertCanSeeTableRecords([$atrasada])->assertCanNotSeeTableRecords([$aberta, $concluida])
            ->call('filtrarCard', 'total')
            ->assertCanSeeTableRecords([$aberta, $concluida, $atrasada]);
    }
}
