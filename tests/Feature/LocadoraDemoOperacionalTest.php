<?php

namespace Tests\Feature;

use App\Dashboards\DashboardRenderer;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Demo\LocadoraDemoOperacional;
use App\Services\Demo\LocadoraDemoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Materiais, Financeiro, Frota e Logistica da Locadora Demo: populados, idempotentes, isolados e com paineis cheios. */
class LocadoraDemoOperacionalTest extends TestCase
{
    use DatabaseTransactions;

    private const TABELAS = ['materials', 'material_stock_movements', 'purchase_orders', 'material_requests', 'account_receivables', 'account_payables', 'frota_abastecimentos', 'frota_multas', 'frota_sinistros', 'frota_trocas_oleo', 'equipment_movements', 'freight_records', 'equipment_pickup_requests'];

    private function contar(string $tenantId): array
    {
        return collect(self::TABELAS)->mapWithKeys(fn ($t) => [$t => DB::table($t)->where('tenant_id', $tenantId)->count()])->all();
    }

    public function test_demo_nasce_com_operacional_populado_idempotente_e_isolado(): void
    {
        $plano = Plan::create(['name' => 'Real '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $real = Tenant::create(['name' => 'Real '.uniqid(), 'slug' => 'real-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $antes = $this->contar((string) $real->id);

        $r = app(LocadoraDemoService::class)->criar('SenhaDemo123');
        $demo = Tenant::withoutGlobalScopes()->where('slug', LocadoraDemoService::SLUG)->firstOrFail();
        $c = $this->contar((string) $demo->id);

        foreach (self::TABELAS as $t) {
            $this->assertGreaterThan(0, $c[$t], $t);
        }
        $this->assertGreaterThanOrEqual(24, $c['materials']);
        $this->assertGreaterThanOrEqual(60, $c['account_receivables']);
        $this->assertSame(6, DB::table('assets')->where('tenant_id', $demo->id)->where('is_vehicle', true)->count());
        $this->assertSame(6, $r['operacional']['frota']['veiculos']);

        (new LocadoraDemoOperacional)->popular($demo);
        $this->assertSame($c, $this->contar((string) $demo->id));
        $this->assertSame($antes, $this->contar((string) $real->id));
    }

    public function test_recusa_empresa_que_nao_e_demo(): void
    {
        $plano = Plan::create(['name' => 'Real '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $real = Tenant::create(['name' => 'Real '.uniqid(), 'slug' => 'real-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);

        $this->expectException(\Throwable::class);
        (new LocadoraDemoOperacional)->popular($real);
    }

    public function test_paineis_da_demo_vem_com_conteudo(): void
    {
        config(['oravel.dashboards_modulo' => true]);
        Cache::flush();
        app(LocadoraDemoService::class)->criar('SenhaDemo123');
        $admin = User::withoutGlobalScopes()->where('email', LocadoraDemoService::EMAIL_ADMIN)->firstOrFail();
        $this->actingAs($admin);

        foreach (['materiais', 'financeiro', 'frota', 'logistica'] as $modulo) {
            $blocos = collect(DashboardRenderer::blocks($modulo, $admin));
            $this->assertGreaterThan(5, $blocos->count(), $modulo);
            $vazios = $blocos->filter(fn ($b) => match ($b['payload']['type']) {
                'timeline' => $b['payload']['events'] === [],
                'ranking', 'table' => $b['payload']['rows'] === [],
                'donut' => ($b['payload']['slices'] ?? $b['payload']['items'] ?? [1]) === [],
                default => false,
            })->pluck('key')->all();
            // Blocos "de problema" (atrasados, parados) podem ficar vazios de proposito; o resto precisa ter dado.
            $this->assertLessThanOrEqual(2, count($vazios), $modulo.': '.implode(', ', $vazios));
        }
    }
}
