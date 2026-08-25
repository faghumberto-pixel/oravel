<?php

namespace Tests\Feature;

use App\Console\Commands\MarcarContasAtrasadas;
use App\Filament\Widgets\FluxoDeCaixaProjetadoWidget;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ContaPagarNotification;
use App\Notifications\ContaReceberNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Pedido do usuário 2026-08-25 (item 4, último do roteiro de artefatos
 * comerciais): "sei, num único lugar, quanto vou receber e pagar nos
 * próximos 30/60/90 dias?". Investigação encontrou AccountReceivableStats
 * e AccountPayableStats como widgets gêmeos, cada um isolado na própria
 * tela -- nunca lado a lado, sem saldo projetado, sem janela de
 * vencimento, sem status "atrasado" automático, e o alerta diário
 * (financeiro:verificar-vencimentos) só cobria contas a pagar.
 */
class FluxoDeCaixaConsolidadoTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Fluxo '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_account_receivables', 'tabela_account_payables'],
        ]);

        return Tenant::create([
            'name' => 'Tenant Fluxo '.uniqid(), 'slug' => 'tenant-fluxo-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    public function test_widget_calcula_saldo_projetado_e_janelas_de_vencimento(): void
    {
        $tenant = $this->makeTenant();

        AccountReceivable::create([
            'tenant_id' => $tenant->id, 'description' => 'Receber dentro de 30d',
            'amount' => 5000, 'due_date' => now()->addDays(15), 'status' => 'pendente',
        ]);
        AccountReceivable::create([
            'tenant_id' => $tenant->id, 'description' => 'Receber fora de 90d',
            'amount' => 9999, 'due_date' => now()->addDays(120), 'status' => 'pendente',
        ]);
        AccountReceivable::create([
            'tenant_id' => $tenant->id, 'description' => 'Já recebido, fora da projeção',
            'amount' => 8888, 'due_date' => now()->addDays(5), 'status' => 'pago',
        ]);

        AccountPayable::create([
            'tenant_id' => $tenant->id, 'description' => 'Pagar dentro de 30d',
            'amount' => 1200, 'due_date' => now()->addDays(10), 'status' => 'atrasado',
        ]);

        $widget = new FluxoDeCaixaProjetadoWidget();
        $stats = $this->invokeGetStats($widget);

        $this->assertSame('R$ 14.999,00', $stats[0]->getValue()); // A Receber (só pendente/atrasado)
        $this->assertSame('R$ 1.200,00', $stats[1]->getValue());  // A Pagar
        $this->assertSame('R$ 13.799,00', $stats[2]->getValue()); // Saldo Projetado (14999 - 1200)
        $this->assertSame('R$ 3.800,00', $stats[3]->getValue());  // Próximos 30 dias: 5000 receber - 1200 pagar
    }

    public function test_marcar_contas_atrasadas_atualiza_status_de_pendente_para_atrasado(): void
    {
        $tenant = $this->makeTenant();

        $vencida = AccountReceivable::create([
            'tenant_id' => $tenant->id, 'description' => 'Vencida',
            'amount' => 500, 'due_date' => now()->subDays(3), 'status' => 'pendente',
        ]);
        $futura = AccountReceivable::create([
            'tenant_id' => $tenant->id, 'description' => 'Futura',
            'amount' => 500, 'due_date' => now()->addDays(3), 'status' => 'pendente',
        ]);
        $vencidaPaga = AccountPayable::create([
            'tenant_id' => $tenant->id, 'description' => 'Vencida mas já paga',
            'amount' => 300, 'due_date' => now()->subDays(3), 'status' => 'pago',
        ]);
        $vencidaPagar = AccountPayable::create([
            'tenant_id' => $tenant->id, 'description' => 'Vencida a pagar',
            'amount' => 300, 'due_date' => now()->subDays(1), 'status' => 'pendente',
        ]);

        $this->artisan(MarcarContasAtrasadas::class)->assertSuccessful();

        $this->assertSame('atrasado', $vencida->fresh()->status);
        $this->assertSame('pendente', $futura->fresh()->status);
        $this->assertSame('pago', $vencidaPaga->fresh()->status);
        $this->assertSame('atrasado', $vencidaPagar->fresh()->status);
    }

    public function test_verificar_vencimentos_notifica_tanto_contas_a_pagar_quanto_a_receber(): void
    {
        Notification::fake();

        $tenant = $this->makeTenant();
        $user = User::create([
            'name' => 'Financeiro', 'email' => 'financeiro-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);

        $payable = AccountPayable::create([
            'tenant_id' => $tenant->id, 'description' => 'Conta a pagar vencendo',
            'amount' => 100, 'due_date' => now()->addDays(3), 'status' => 'pendente',
        ]);
        $receivable = AccountReceivable::create([
            'tenant_id' => $tenant->id, 'description' => 'Conta a receber vencendo',
            'amount' => 200, 'due_date' => now()->addDays(3), 'status' => 'pendente',
        ]);

        $this->artisan('financeiro:verificar-vencimentos')->assertSuccessful();

        Notification::assertSentTo($user, ContaPagarNotification::class);
        Notification::assertSentTo($user, ContaReceberNotification::class);
    }

    /** @return \Filament\Widgets\StatsOverviewWidget\Stat[] */
    private function invokeGetStats(FluxoDeCaixaProjetadoWidget $widget): array
    {
        $method = new \ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        return $method->invoke($widget);
    }
}
