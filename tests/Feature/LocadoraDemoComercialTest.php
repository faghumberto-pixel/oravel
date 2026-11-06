<?php

namespace Tests\Feature;

use App\Dashboards\Blocks\Comercial as B;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Demo\LocadoraDemoComercial;
use App\Services\Demo\LocadoraDemoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Comercial da Locadora Demo: >= 30 registros por lista, idempotente, so' na demo, e blocos novos renderizam. */
class LocadoraDemoComercialTest extends TestCase
{
    use DatabaseTransactions;

    private function contar(string $tenantId): array
    {
        return collect(['crm_leads', 'quotes', 'proposta_comerciais', 'solicitacoes_locacao', 'client_feedbacks', 'clients'])
            ->mapWithKeys(fn ($t) => [$t => DB::table($t)->where('tenant_id', $tenantId)->count()])->all();
    }

    public function test_demo_nasce_com_comercial_populado_e_idempotente(): void
    {
        $plano = Plan::create(['name' => 'Real '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_crm_leads']]);
        $real = Tenant::create(['name' => 'Real '.uniqid(), 'slug' => 'real-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $antes = $this->contar((string) $real->id);

        $r = app(LocadoraDemoService::class)->criar('SenhaDemo123');
        $demo = Tenant::withoutGlobalScopes()->where('slug', LocadoraDemoService::SLUG)->firstOrFail();

        $c = $this->contar((string) $demo->id);
        foreach (['crm_leads', 'quotes', 'proposta_comerciais', 'solicitacoes_locacao', 'client_feedbacks', 'clients'] as $t) {
            $this->assertGreaterThanOrEqual(30, $c[$t], $t);
        }
        $this->assertSame($c['crm_leads'], $r['comercial']['leads']);

        // Rodar de novo nao duplica.
        (new LocadoraDemoComercial)->popular($demo);
        $this->assertSame($c, $this->contar((string) $demo->id));

        // Empresa real intocada.
        $this->assertSame($antes, $this->contar((string) $real->id));
    }

    public function test_recusa_empresa_que_nao_e_demo(): void
    {
        $plano = Plan::create(['name' => 'Real '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $real = Tenant::create(['name' => 'Real '.uniqid(), 'slug' => 'real-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);

        $this->expectException(\Throwable::class);
        (new LocadoraDemoComercial)->popular($real);
    }

    public function test_blocos_do_comercial_geram_os_formatos_esperados(): void
    {
        app(LocadoraDemoService::class)->criar('SenhaDemo123');
        $this->actingAs(User::withoutGlobalScopes()->where('email', LocadoraDemoService::EMAIL_ADMIN)->firstOrFail());

        $esperado = [
            B\PipelineEmAberto::class => 'kpi', B\PropostasEmAberto::class => 'kpi', B\OrcamentosPorSituacao::class => 'donut',
            B\OrigemDosLeads::class => 'donut', B\MapaDosLeads::class => 'map', B\EsteiraDePropostas::class => 'funnel',
            B\OrigemPorMes::class => 'heat', B\ProximosCompromissos::class => 'timeline', B\UltimosFechamentos::class => 'timeline',
            B\VendasPorVendedor::class => 'ranking', B\ContratosAtivos::class => 'kpi', B\ConversaoDeOrcamentos::class => 'kpi',
        ];
        foreach ($esperado as $classe => $tipo) {
            $dados = (new $classe)->data([]);
            $this->assertSame($tipo, $dados['type'], $classe);
        }

        $this->assertNotEmpty((new B\UltimosFechamentos)->data([])['events']);
        $this->assertNotEmpty((new B\ProximosCompromissos)->data([])['events']);
        $this->assertNotEmpty((new B\MapaDosLeads)->data([])['points'] ?? (new B\MapaDosLeads)->data([])['pins'] ?? []);
        $this->assertNotNull((new B\ContratosAtivos)->data([])['spark']);
    }
}
