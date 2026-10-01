<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ImplementationChargeResource\Pages\ListImplementationCharges;
use App\Filament\Central\Resources\TenantResource\Pages\ListTenants;
use App\Filament\Central\Widgets\ImplementationStats;
use App\Models\ImplementationCharge;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Gestão da implantação na Central: lista de parcelas, resumo e cancelamento. */
class ImplementationChargeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actAsSuperAdmin(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();

        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));
    }

    private function makeTenant(float $fee = 900): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Gestao '.uniqid(), 'price' => 600, 'base_price' => 600, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
            'implementation_fee' => $fee, 'implementation_installments' => 2,
        ]);

        return Tenant::create([
            'name' => 'Cliente '.uniqid(), 'slug' => 'cliente-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'trial',
        ]);
    }

    private function charge(Tenant $tenant, int $n, string $status, array $extra = []): ImplementationCharge
    {
        return ImplementationCharge::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->id, 'installment_number' => $n, 'installments_total' => 2, 'amount' => 450,
            'due_date' => now()->addDays(7), 'status' => $status, 'asaas_payment_id' => "pay_{$n}_".uniqid(),
        ], $extra));
    }

    public function test_list_and_stats_show_open_received_and_overdue_amounts(): void
    {
        $this->actAsSuperAdmin();
        $tenant = $this->makeTenant();
        $paid = $this->charge($tenant, 1, ImplementationCharge::PAGO);
        $late = $this->charge($tenant, 2, ImplementationCharge::ATRASADO);

        Livewire::test(ListImplementationCharges::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$paid, $late]);

        Livewire::test(ImplementationStats::class)
            ->assertSee('R$ 450,00')   // recebido e também a receber/atraso: 1 parcela de cada
            ->assertSee('1 parcela(s) paga(s)')
            ->assertSee('1 parcela(s) atrasada(s)');
    }

    public function test_cancel_action_cancels_in_asaas_and_only_then_marks_cancelled(): void
    {
        $this->actAsSuperAdmin();
        config(['services.asaas.api_key' => 'test-key']);
        $tenant = $this->makeTenant();
        $charge = $this->charge($tenant, 1, ImplementationCharge::PENDENTE, ['asaas_payment_id' => 'pay_cancel_me']);

        // 1ª tentativa o Asaas recusa (ex.: já paga) e a 2ª aceita.
        Http::fake(['*/payments/pay_cancel_me' => Http::sequence()
            ->push(['deleted' => false], 200)
            ->push(['deleted' => true, 'id' => 'pay_cancel_me'], 200)]);

        Livewire::test(ListImplementationCharges::class)->callTableAction('cancel_charge', $charge);
        $this->assertSame(ImplementationCharge::PENDENTE, $charge->refresh()->status);

        Livewire::test(ListImplementationCharges::class)->callTableAction('cancel_charge', $charge);
        $this->assertSame(ImplementationCharge::CANCELADO, $charge->refresh()->status);
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/payments/pay_cancel_me'));
    }

    public function test_tenant_implementation_summary_and_tenants_list(): void
    {
        $this->actAsSuperAdmin();

        $none = $this->makeTenant(0);
        $notCharged = $this->makeTenant();
        $paid = $this->makeTenant();
        $this->charge($paid, 1, ImplementationCharge::PAGO);
        $this->charge($paid, 2, ImplementationCharge::PAGO);
        $partial = $this->makeTenant();
        $this->charge($partial, 1, ImplementationCharge::PAGO);
        $this->charge($partial, 2, ImplementationCharge::PENDENTE);
        $late = $this->makeTenant();
        $this->charge($late, 1, ImplementationCharge::ATRASADO);

        $this->assertSame('Sem implantação', $none->implementationSummary()['label']);
        $this->assertSame('Não cobrada', $notCharged->implementationSummary()['label']);
        $this->assertSame('Paga', $paid->implementationSummary()['label']);
        $this->assertSame('Parcialmente paga', $partial->implementationSummary()['label']);
        $this->assertSame('Atrasada', $late->implementationSummary()['label']);

        Livewire::test(ListTenants::class)->assertSuccessful()->assertSee('Parcialmente paga');
    }
}
