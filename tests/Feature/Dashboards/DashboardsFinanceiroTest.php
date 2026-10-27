<?php

namespace Tests\Feature\Dashboards;

use App\Dashboards\Blocks\Financeiro\APagarNoMes;
use App\Dashboards\Blocks\Financeiro\AReceberNoMes;
use App\Dashboards\Blocks\Financeiro\PagamentosAtrasados;
use App\Dashboards\Blocks\Financeiro\RecebidoNoMes;
use App\Dashboards\Blocks\Financeiro\RecebimentosAtrasados;
use App\Dashboards\DashboardRenderer;
use App\Filament\Pages\VisaoGeralFinanceiro;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardsFinanceiroTest extends TestCase
{
    use DatabaseTransactions;

    private const FEATURES = ['tabela_account_receivables' => true, 'tabela_account_payables' => true];

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.dashboards_modulo' => true]);
        Cache::flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function empresa(array $features = self::FEATURES): array
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => 'E '.uniqid(), 'slug' => 'f-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function criar(string $modelo, array $attrs): Model
    {
        return $modelo::unguarded(fn () => $modelo::create($attrs));
    }

    private function receber(Tenant $t, float $valor, string $status, string $venc, array $extra = []): AccountReceivable
    {
        return $this->criar(AccountReceivable::class, $extra + ['tenant_id' => $t->id, 'description' => 'Locação', 'amount' => $valor, 'due_date' => $venc, 'status' => $status]);
    }

    private function pagar(Tenant $t, float $valor, string $status, string $venc, array $extra = []): AccountPayable
    {
        return $this->criar(AccountPayable::class, $extra + ['tenant_id' => $t->id, 'description' => 'Fornecedor', 'amount' => $valor, 'due_date' => $venc, 'status' => $status]);
    }

    public function test_so_os_blocos_dos_modulos_contratados(): void
    {
        [, $admin] = $this->empresa(['tabela_account_receivables' => true]);
        $this->actingAs($admin);

        $chaves = array_column(DashboardRenderer::blocks('financeiro', $admin), 'key');

        $this->assertContains('financeiro.a_receber_mes', $chaves);
        $this->assertNotContains('financeiro.a_pagar_mes', $chaves);
        $this->assertNotContains('financeiro.recebido_x_pago', $chaves);
    }

    public function test_sem_financeiro_no_plano_a_pagina_nao_existe(): void
    {
        [, $admin] = $this->empresa(['tabela_assets' => true]);
        $this->actingAs($admin);

        $this->assertFalse(VisaoGeralFinanceiro::canAccess());
    }

    public function test_a_receber_do_mes_ignora_pagos_outros_meses_e_outro_tenant(): void
    {
        [$a, $adminA] = $this->empresa();
        [$b] = $this->empresa();
        $meio = now()->startOfMonth()->addDays(10)->toDateString();
        $this->receber($a, 100, 'pendente', $meio);
        $this->receber($a, 200, 'atrasado', $meio);
        $this->receber($a, 900, 'pago', $meio);
        $this->receber($a, 700, 'pendente', now()->addMonths(2)->toDateString());
        $this->receber($b, 5000, 'pendente', $meio);
        $this->actingAs($adminA);

        $p = (new AReceberNoMes)->data([]);

        $this->assertStringContainsString('300,00', $p['value']);
        $this->assertStringContainsString('2 títulos', $p['delta']);
    }

    public function test_recebido_no_mes_compara_com_o_mes_anterior(): void
    {
        [$t, $admin] = $this->empresa();
        $this->receber($t, 150, 'pago', now()->toDateString(), ['payment_date' => now()->toDateString()]);
        $this->receber($t, 100, 'pago', now()->subMonth()->toDateString(), ['payment_date' => now()->subMonth()->toDateString()]);
        $this->actingAs($admin);

        $p = (new RecebidoNoMes)->data([]);

        $this->assertStringContainsString('150,00', $p['value']);
        $this->assertStringContainsString('+50%', $p['delta']);
        $this->assertSame('up', $p['tone']);
    }

    public function test_a_pagar_do_mes(): void
    {
        [$t, $admin] = $this->empresa();
        $this->pagar($t, 400, 'pendente', now()->startOfMonth()->addDays(5)->toDateString());
        $this->pagar($t, 999, 'pago', now()->startOfMonth()->addDays(5)->toDateString());
        $this->actingAs($admin);

        $this->assertStringContainsString('400,00', (new APagarNoMes)->data([])['value']);
    }

    public function test_atrasados_listam_so_vencidos_nao_pagos_e_marcam_a_gravidade(): void
    {
        [$t, $admin] = $this->empresa();
        $this->receber($t, 100, 'pendente', now()->subDays(30)->toDateString());
        $this->receber($t, 100, 'pendente', now()->subDays(3)->toDateString());
        $this->receber($t, 100, 'pago', now()->subDays(40)->toDateString());
        $this->receber($t, 100, 'pendente', now()->addDays(3)->toDateString());
        $this->pagar($t, 50, 'atrasado', now()->subDays(2)->toDateString());
        $this->actingAs($admin);

        $receber = (new RecebimentosAtrasados)->data([])['rows'];
        $pagar = (new PagamentosAtrasados)->data([])['rows'];

        $this->assertCount(2, $receber);
        $this->assertSame('crit', $receber[0][2]['pill']); // o de 30 dias vem primeiro e é crítico
        $this->assertSame('warn', $receber[1][2]['pill']);
        $this->assertCount(1, $pagar);
    }

    public function test_a_pagina_renderiza(): void
    {
        [$t, $admin] = $this->empresa();
        $this->receber($t, 100, 'pendente', now()->subDays(2)->toDateString());
        $this->actingAs($admin);

        Livewire::test(VisaoGeralFinanceiro::class)
            ->assertSee('A receber no mês')
            ->assertSee('Recebimentos em atraso')
            ->assertSee('Recebido x pago por mês');
    }
}
