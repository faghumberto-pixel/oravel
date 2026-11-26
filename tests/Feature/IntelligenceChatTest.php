<?php

namespace Tests\Feature;

use App\Domain\Intelligence\Models\IntelligenceBenchmark;
use App\Domain\Intelligence\Models\IntelligenceImport;
use App\Domain\Intelligence\Models\IntelligenceIndicator;
use App\Domain\Intelligence\Models\IntelligenceSnapshot;
use App\Domain\Intelligence\Services\DadosDeExemplo;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Services\CotaDiariaIa;
use App\Livewire\Assistente\IntelligenceChat;
use App\Models\AssistenteRelatorio;
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

    private function contaDeTeste(Tenant $t, bool $contratou): void
    {
        IntelligenceTrial::withoutGlobalScopes()->create([
            'tenant_id' => $t->id, 'user_id' => $this->admin($t)->id, 'nome' => 'Maria', 'empresa' => $t->name,
            'whatsapp' => '+5519'.random_int(900000000, 999999999), 'status' => 'trial', 'contracted_at' => $contratou ? now() : null,
        ]);
    }

    private function fakeIa(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $json = json_encode(['answer' => 'Tudo certo.', 'sql' => 'SELECT 1 AS um', 'insights' => [], 'suggested_actions' => []]);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => $json]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]])]);
    }

    public function test_teste_gratis_tem_1_pergunta_por_dia_e_a_segunda_nem_chama_a_ia(): void
    {
        $t = $this->tenant('Em teste', [IntelligenceSnapshot::saasFeatureKey()]);
        $this->contaDeTeste($t, false);
        $this->fakeIa();
        $this->actingAs(User::where('tenant_id', $t->id)->first());

        $c = Livewire::test(IntelligenceChat::class)->set('question', 'Primeira?')->call('send');
        Http::assertSentCount(1);

        $c->set('question', 'Segunda?')->call('send');
        Http::assertSentCount(1); // bloqueada antes de gastar IA
        $aviso = collect($c->get('messages'))->last()['content'];
        $this->assertStringContainsString('1 pergunta por dia', $aviso);
        $this->assertStringContainsString('Assine o Plano Básico', $aviso);
        $this->assertStringContainsString('10 consultas por dia', $aviso);
        $this->assertStringContainsString(route('intelligence.trial.plano'), $aviso);

        // outro usuario do mesmo cliente nao renova a cota
        $outro = User::create(['name' => 'Outro', 'email' => 'o-'.uniqid().'@teste.com', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $outro->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $outro->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        $this->actingAs($outro);
        Livewire::test(IntelligenceChat::class)->set('question', 'Terceira?')->call('send');
        Http::assertSentCount(1);
    }

    public function test_plano_basico_contratado_tem_10_por_dia_e_depois_bloqueia(): void
    {
        $t = $this->tenant('Contratou', [IntelligenceSnapshot::saasFeatureKey()]);
        $this->contaDeTeste($t, true);
        $this->fakeIa();
        $this->actingAs(User::where('tenant_id', $t->id)->first());

        $c = Livewire::test(IntelligenceChat::class);
        foreach (range(1, 10) as $i) {
            $c->set('question', 'P'.$i)->call('send');
        }
        Http::assertSentCount(10);

        $c->set('question', 'P11')->call('send');
        Http::assertSentCount(10);
        $aviso = collect($c->get('messages'))->last()['content'];
        $this->assertStringContainsString('10 perguntas de hoje', $aviso);
        $this->assertStringNotContainsString('Assine', $aviso); // quem já assinou não recebe o convite
    }

    public function test_falha_da_ia_devolve_a_pergunta_do_dia(): void
    {
        $t = $this->tenant('Falha', [IntelligenceSnapshot::saasFeatureKey()]);
        $this->contaDeTeste($t, false);
        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response('erro', 500)]);
        $this->actingAs(User::where('tenant_id', $t->id)->first());

        Livewire::test(IntelligenceChat::class)->set('question', 'Vai falhar')->call('send');

        $this->assertTrue(app(CotaDiariaIa::class)->reservar($t->id), 'a cota deveria ter sido devolvida');
    }

    public function test_ia_e_avisada_quando_so_ha_dados_de_demonstracao(): void
    {
        $t = $this->tenant('Com exemplo', [IntelligenceSnapshot::saasFeatureKey()]);
        $this->fakeIa();
        app(DadosDeExemplo::class)->gerar($t);
        $this->actingAs($this->admin($t));

        Livewire::test(IntelligenceChat::class)->set('question', 'Como estou?')->call('send');
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'DEMONSTRAÇÃO'));

        // depois de limpar, o aviso some
        app(DadosDeExemplo::class)->limpar($t);
        $this->snapshot($t, '2026-09-30', 81.0);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['answer' => 'ok', 'sql' => '', 'insights' => [], 'suggested_actions' => []])]], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]])]);
        Livewire::test(IntelligenceChat::class)->set('question', 'E agora?')->call('send');
        Http::assertNotSent(fn ($r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'DEMONSTRAÇÃO'));
    }

    public function test_imprimir_conversa_gera_relatorio_com_perguntas_e_respostas(): void
    {
        $t = $this->tenant('Imprime', [IntelligenceSnapshot::saasFeatureKey()]);
        $this->fakeIa();
        $this->actingAs($this->admin($t));

        $c = Livewire::test(IntelligenceChat::class);
        $c->call('imprimirConversa'); // sem pergunta ainda: nada é criado
        $this->assertSame(0, AssistenteRelatorio::withoutGlobalScopes()->where('tenant_id', $t->id)->count());

        $c->set('question', 'Como está minha empresa?')->call('send')->call('imprimirConversa');

        $r = AssistenteRelatorio::withoutGlobalScopes()->where('tenant_id', $t->id)->where('titulo', 'like', 'Conversa com o assistente%')->firstOrFail();
        $this->assertSame(['Pergunta', 'Resposta'], $r->colunas);
        $this->assertSame('Como está minha empresa?', $r->linhas[0][0]);
        $this->assertStringContainsString('Tudo certo.', $r->linhas[0][1]);
        $this->assertSame($t->id, $r->tenant_id);
        $ultima = collect($c->get('messages'))->last();
        $this->assertSame($r->url(), $ultima['relatorio']['url']);
        $this->get($r->url())->assertOk()->assertSee('Imprimir / salvar PDF')->assertSee('Como está minha empresa?');
    }
}
