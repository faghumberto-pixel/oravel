<?php

namespace Tests\Feature;

use App\Domain\Intelligence\Models\IntelligenceBenchmark;
use App\Domain\Intelligence\Models\IntelligenceImport;
use App\Domain\Intelligence\Models\IntelligenceIndicator;
use App\Domain\Intelligence\Models\IntelligenceSnapshot;
use App\Livewire\Assistente\IntelligenceChat;
use App\Models\IaConsumo;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\IntelligenceChatService;
use App\Support\Assistente\TenantSql;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class IntelligenceChatTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(string $name, array $features): Tenant
    {
        $plan = Plan::create(['name' => 'P '.$name, 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);

        return Tenant::create(['name' => $name, 'slug' => str($name)->slug().'-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function admin(Tenant $t): User
    {
        $u = User::create(['name' => 'Admin '.$t->name, 'email' => 'adm-'.uniqid().'@teste.com', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return $u;
    }

    private function snapshot(Tenant $t, string $data, float $disponibilidade): void
    {
        $imp = IntelligenceImport::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'status' => 'concluida']);
        $s = IntelligenceSnapshot::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'import_id' => $imp->id, 'data_referencia' => $data, 'dados' => ['porte' => 'Médio'], 'hash' => hash('sha256', $t->id.$data)]);
        IntelligenceIndicator::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'snapshot_id' => $s->id, 'chave' => 'disponibilidade_frota', 'valor' => $disponibilidade, 'unidade' => '%', 'meta' => ['area' => 'frota', 'ruim' => 70, 'bom' => 95]]);
    }

    public function test_sql_do_agente_so_devolve_dado_da_propria_empresa_e_benchmark_anonimo(): void
    {
        $a = $this->tenant('Empresa A', []);
        $b = $this->tenant('Empresa B', []);
        $this->snapshot($a, '2026-09-30', 81.0);
        $this->snapshot($b, '2026-09-30', 99.0);
        IntelligenceBenchmark::create(['indicador' => 'disponibilidade_frota', 'periodo' => '2026-09-01', 'amostra' => 12, 'mediana' => 88]);

        [$linhas, $erro] = TenantSql::run($a->id, 'SELECT valor FROM intelligence_indicators', IntelligenceChatService::TABELAS);
        $this->assertNull($erro, (string) $erro);
        $this->assertSame([81.0], array_map(fn ($l) => (float) $l['valor'], $linhas));

        [$bench, $erro] = TenantSql::run($a->id, "SELECT mediana FROM intelligence_benchmarks WHERE indicador = 'disponibilidade_frota'", IntelligenceChatService::TABELAS);
        $this->assertNull($erro, (string) $erro);
        $this->assertSame(88.0, (float) $bench[0]['mediana']);

        // tabela do ERP nao existe para o agente, mesmo existindo no banco
        [, $erro] = TenantSql::run($a->id, 'SELECT name FROM tenants', IntelligenceChatService::TABELAS);
        $this->assertNotNull($erro);
    }

    public function test_acesso_exige_cliente_com_o_pacote_e_nunca_super_admin(): void
    {
        $sem = $this->tenant('Sem pacote', []);
        $com = $this->tenant('Com pacote', [IntelligenceSnapshot::saasFeatureKey()]);

        $this->actingAs($this->admin($sem));
        Livewire::test(IntelligenceChat::class)->assertForbidden();

        $this->actingAs($this->admin($com));
        Livewire::test(IntelligenceChat::class)->assertOk();

        $super = User::create(['name' => 'Super', 'email' => 'super@teste.com', 'password' => bcrypt('x')]);
        config(['oravel.super_admins' => ['super@teste.com']]);
        $this->actingAs($super);
        Livewire::test(IntelligenceChat::class)->assertForbidden();
    }

    public function test_conversa_completa_manda_o_resumo_da_empresa_e_responde_com_os_dados_dela(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $a = $this->tenant('Empresa A', [IntelligenceSnapshot::saasFeatureKey()]);
        $b = $this->tenant('Empresa B', [IntelligenceSnapshot::saasFeatureKey()]);
        $this->snapshot($a, '2026-08-31', 78.0);
        $this->snapshot($a, '2026-09-30', 85.0);
        $this->snapshot($b, '2026-09-30', 99.5);

        $json = json_encode(['answer' => 'A disponibilidade subiu.', 'sql' => "SELECT s.data_referencia AS data, i.valor AS valor FROM intelligence_indicators i JOIN intelligence_snapshots s ON s.id = i.snapshot_id WHERE i.chave = 'disponibilidade_frota' ORDER BY s.data_referencia", 'insights' => [], 'suggested_actions' => []]);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => $json]], 'usage' => ['input_tokens' => 100, 'output_tokens' => 20]])]);

        $this->actingAs($this->admin($a));
        $c = Livewire::test(IntelligenceChat::class)->set('question', 'Minha disponibilidade aumentou?')->call('send');

        $msg = collect($c->get('messages'))->last();
        $this->assertSame('A disponibilidade subiu.', $msg['content']);
        $this->assertSame([78.0, 85.0], array_map(fn ($l) => (float) $l['valor'], $msg['data']));
        $this->assertNotContains(99.5, array_map(fn ($l) => (float) $l['valor'], $msg['data']));
        $this->assertNotEmpty($msg['relatorio']);

        Http::assertSent(function ($r) {
            $sistema = is_array($r['system']) ? json_encode($r['system']) : (string) $r['system'];
            $texto = json_encode($r['messages']);

            return str_contains($sistema, 'intelligence_snapshots')
                && ! str_contains($sistema, 'maintenance_orders')
                && str_contains($sistema, 'SOMENTE DE CONSULTA')
                && str_contains($texto, 'disponibilidade_frota') && str_contains($texto, 'valor\\":85');
        });
        $this->assertSame($a->id, IaConsumo::where('area', 'intelligence')->latest('created_at')->value('tenant_id'));
    }
}
