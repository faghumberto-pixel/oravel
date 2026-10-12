<?php

namespace Tests\Feature\Asaas;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A tela de conta bloqueada mostra dias de atraso inteiros, nunca fração (Carbon 3 devolve float). */
class BlockedPageDaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_days_overdue_are_shown_as_whole_number(): void
    {
        $plan = Plan::create([
            'name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);
        $tenant = Tenant::create([
            'name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active',
            'asaas_payment_status' => Tenant::PAYMENT_STATUS_ATRASADO,
            'asaas_overdue_since' => now()->subDays(6)->subHours(7)->subMinutes(13),
        ]);

        $html = view('checkout.blocked', ['tenant' => $tenant])->render();

        $this->assertStringContainsString('há 6 dias', $html);
        $this->assertDoesNotMatchRegularExpression('/há \d+\.\d+ dias/', $html);
    }
}
