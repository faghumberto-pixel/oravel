<?php

namespace Tests\Feature;

use App\Filament\Central\Pages\TenantTree;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TenantTreeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_arvore_conta_ativos_da_empresa_mesmo_sem_cliente(): void
    {
        $plan = Plan::create(['name' => 'P', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $tenant = Tenant::create(['name' => 'Frota sem cliente', 'slug' => 'fsc-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        foreach (['A-1', 'A-2', 'A-3'] as $p) {
            $a = new Asset(['patrimonio' => $p, 'name' => $p, 'status' => 'disponivel']);
            $a->tenant_id = $tenant->id;
            $a->save();
        }

        $node = (new TenantTree)->getTenantTree()->first(fn ($n) => $n['tenant']->id === $tenant->id);

        $this->assertSame(3, $node['assets_total']);
        $this->assertCount(0, $node['clients']);
    }
}
