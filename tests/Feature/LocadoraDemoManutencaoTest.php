<?php

namespace Tests\Feature;

use App\Dashboards\Blocks\Manutencao as B;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Demo\LocadoraDemoManutencao;
use App\Services\Demo\LocadoraDemoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Manutencao da Locadora Demo: atividade recente, idempotente, isolada, e com todos os cards da Visao geral preenchidos. */
class LocadoraDemoManutencaoTest extends TestCase
{
    use DatabaseTransactions;

    private function contar(string $tenantId): array
    {
        return ['os' => DB::table('maintenance_orders')->where('tenant_id', $tenantId)->count(), 'defeitos' => DB::table('reported_problems')->where('tenant_id', $tenantId)->count(),
            'paradas' => DB::table('asset_downtime_events')->where('tenant_id', $tenantId)->count(), 'pecas' => DB::table('parts_requests')->where('tenant_id', $tenantId)->count()];
    }

    public function test_idempotente_e_isolada(): void
    {
        $plano = Plan::create(['name' => 'Real '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $real = Tenant::create(['name' => 'Real '.uniqid(), 'slug' => 'real-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $antes = $this->contar((string) $real->id);

        $r = app(LocadoraDemoService::class)->criar('SenhaDemo123');
        $demo = Tenant::withoutGlobalScopes()->where('slug', LocadoraDemoService::SLUG)->firstOrFail();
        $c = $this->contar((string) $demo->id);

        $this->assertSame(7, $r['manutencao']['agenda_de_hoje']);
        $this->assertGreaterThan(50, $r['manutencao']['concluidas_60_dias']);
        $this->assertSame(4, DB::table('maintenance_orders')->where('tenant_id', $demo->id)->where('internal_status', 'aguardando_peca')->count());

        (new LocadoraDemoManutencao)->popular($demo);
        $this->assertSame($c, $this->contar((string) $demo->id));
        $this->assertSame($antes, $this->contar((string) $real->id));
    }

    public function test_recusa_empresa_que_nao_e_demo(): void
    {
        $plano = Plan::create(['name' => 'Real '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $real = Tenant::create(['name' => 'Real '.uniqid(), 'slug' => 'real-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);

        $this->expectException(\Throwable::class);
        (new LocadoraDemoManutencao)->popular($real);
    }

    public function test_cards_da_visao_geral_vem_preenchidos(): void
    {
        app(LocadoraDemoService::class)->criar('SenhaDemo123');
        $this->actingAs(User::withoutGlobalScopes()->where('email', LocadoraDemoService::EMAIL_ADMIN)->firstOrFail());

        $this->assertGreaterThanOrEqual(7, (new B\AtendimentosHoje)->data([])['value']);
        $this->assertNotSame('—', (new B\AtendimentosNoPrazo)->data([])['value']);
        $this->assertNotSame('—', (new B\CustoMedio)->data([])['value']);
        $this->assertNotSame('0', (new B\KmRodados)->data([])['value']);
        $this->assertGreaterThanOrEqual(2, (new B\EquipamentosParados)->data([])['value']);
        $this->assertGreaterThan(0, (int) (new B\RetornoMaquina)->data([])['bar']['pct']);
        $this->assertNotEmpty((new B\DefeitosFrequentes)->data([])['rows']);
        $this->assertCount(4, (new B\AguardandoPeca)->data([])['rows']);
        $this->assertNotEmpty((new B\AgendaDeHoje)->data([])['events']);
        $this->assertGreaterThanOrEqual(3, count((new B\CargaPorTecnico)->data([])['rows']));
        $this->assertGreaterThan(0, max((new B\TempoPrimeiroAtendimento)->data([])['values'] ?? [1]));
    }
}
