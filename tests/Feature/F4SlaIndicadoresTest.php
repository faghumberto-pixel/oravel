<?php

namespace Tests\Feature;

use App\Dashboards\BlockRegistry;
use App\Dashboards\Blocks\Manutencao\AtendimentosNoPrazo;
use App\Dashboards\Blocks\Manutencao\SlaResolucaoCumprida;
use App\Dashboards\Blocks\Manutencao\SlaTempoMedioResolucao;
use App\Dashboards\Blocks\Manutencao\SlaVencidosAgora;
use App\Filament\Pages\PainelSlaEmergencia;
use App\Models\AbcMatrix;
use App\Models\Asset;
use App\Models\Client;
use App\Models\CriticalityLevel;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GestaoAVistaService;
use App\Services\Sla\SlaKpis;
use App\Services\Sla\SlaService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** F4: indicadores de SLA por dimensão, painel com período e exportação, blocos de dashboard e painéis antigos unificados. */
class F4SlaIndicadoresTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $admin;

    private User $ana;

    private User $beto;

    protected function setUp(): void
    {
        parent::setUp();
        SlaService::esquecer();
        Carbon::setTestNow('2026-11-09 12:00:00');
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets' => true, 'tabela_maintenance_orders' => true, 'tabela_sla_policies' => true]]);
        $this->tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'f4-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $this->admin = $this->usuario('Admin', 'admin');
        $this->ana = $this->usuario('Ana Técnica');
        $this->beto = $this->usuario('Beto Técnico');
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        SlaPolicy::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'priority_code' => 'alta', 'resolution_minutes' => 100, 'is_active' => true]);
        SlaPolicy::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'priority_code' => 'media', 'resolution_minutes' => 100, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function usuario(string $nome, ?string $papel = null): User
    {
        $u = User::create(['name' => $nome, 'email' => str($nome)->slug().'-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        $u->forceFill(['email_verified_at' => now()])->save();
        if ($papel) {
            $u->assignRole(Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]));
        }

        return $u;
    }

    private function ativo(?string $nivel = null): Asset
    {
        $a = Asset::create(['tenant_id' => $this->tenant->id, 'name' => 'Gerador '.uniqid(), 'tag' => 'AST-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL]);
        if ($nivel) {
            CriticalityLevel::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $this->tenant->id, 'code' => $nivel], ['name' => 'Nível '.$nivel, 'color' => '#f00', 'is_urgent' => $nivel === 'A', 'sort_order' => 1, 'default_priority' => 'alta']);
            AbcMatrix::create(['tenant_id' => $this->tenant->id, 'asset_id' => $a->id, 'nivel' => $nivel, 'descricao' => 'x']);
        }

        return $a;
    }

    /** OS concluída `duracao` minutos depois de aberta (aberta há `idade` minutos). Prazo de resolução: 100 min. */
    private function concluida(User $tec, int $duracao, int $idade = 600, ?Client $cliente = null, string $tipo = 'Preventiva', ?Asset $ativo = null, string $prio = 'alta'): MaintenanceOrder
    {
        $criada = now()->subMinutes($idade);
        $o = new MaintenanceOrder;
        $o->forceFill(['tenant_id' => $this->tenant->id, 'asset_id' => ($ativo ?? $this->ativo())->id, 'technician_id' => $tec->id, 'client_id' => $cliente?->id, 'description' => 'x', 'maintenance_type' => $tipo, 'status' => 'Concluída',
            'priority' => $prio, 'created_at' => $criada, 'updated_at' => $criada, 'first_response_at' => $criada->copy()->addMinutes(5), 'started_at' => $criada->copy()->addMinutes(10), 'finished_at' => $criada->copy()->addMinutes($duracao)]);
        $o->save();

        return $o;
    }

    private function aberta(int $idade, string $prio = 'alta'): MaintenanceOrder
    {
        $o = new MaintenanceOrder;
        $o->forceFill(['tenant_id' => $this->tenant->id, 'asset_id' => $this->ativo()->id, 'description' => 'x', 'maintenance_type' => 'Preventiva', 'status' => 'Pendente', 'priority' => $prio, 'created_at' => now()->subMinutes($idade), 'updated_at' => now()]);
        $o->save();

        return $o;
    }

    public function test_quebra_por_tecnico_calcula_percentual_tempo_medio_e_vencidos_agora(): void
    {
        $this->concluida($this->ana, 60);  // no prazo (60 de 100)
        $this->concluida($this->ana, 80);  // no prazo
        $this->concluida($this->beto, 150); // estourou
        $this->aberta(300);                // aberta e vencida (sem técnico)

        $linhas = collect(SlaKpis::porDimensao((string) $this->tenant->id, 'tecnico', 30, 'resolution'))->keyBy('rotulo');

        $this->assertSame(100, $linhas['Ana Técnica']['pct']);
        $this->assertSame(2, $linhas['Ana Técnica']['total']);
        $this->assertSame(70, $linhas['Ana Técnica']['tempo_medio']);
        $this->assertSame(0, $linhas['Beto Técnico']['pct']);
        $this->assertSame(1, $linhas['Sem técnico']['vencidos']);

        $this->assertSame('Beto Técnico', SlaKpis::porDimensao((string) $this->tenant->id, 'tecnico', 30, 'resolution')[0]['rotulo'], 'pior percentual primeiro');
    }

    public function test_outras_dimensoes_prioridade_criticidade_tipo_e_cliente(): void
    {
        $cliente = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Construtora Norte']);
        $this->concluida($this->ana, 60, 600, $cliente, 'Preventiva', $this->ativo('A'), 'alta');
        $this->concluida($this->ana, 150, 600, null, 'Avaria', $this->ativo('B'), 'media');

        $t = (string) $this->tenant->id;
        $this->assertEqualsCanonicalizing(['Alta', 'Média'], array_column(SlaKpis::porDimensao($t, 'prioridade', 30), 'rotulo'));
        $this->assertEqualsCanonicalizing(['Nível A', 'Nível B'], array_column(SlaKpis::porDimensao($t, 'criticidade', 30), 'rotulo'));
        $this->assertEqualsCanonicalizing(['Preventiva', 'Avaria'], array_column(SlaKpis::porDimensao($t, 'tipo', 30), 'rotulo'));
        $porCliente = collect(SlaKpis::porDimensao($t, 'cliente', 30))->keyBy('rotulo');
        $this->assertSame(100, $porCliente['Construtora Norte']['pct']);
        $this->assertSame(0, $porCliente['Sem cliente']['pct']);
    }

    public function test_tempo_medio_nao_conta_o_tempo_parado(): void
    {
        $os = $this->aberta(200);            // aberta às 08:40
        $os->update(['internal_status' => 'aguardando_peca']); // para às 12:00
        Carbon::setTestNow('2026-11-09 13:00:00');
        $os->update(['internal_status' => 'em_manutencao']);   // 60 min parado
        Carbon::setTestNow('2026-11-09 13:40:00');
        $os->update(['status' => 'Concluída']);                // 08:40 a 13:40 = 300 min, menos 60 de pausa

        $this->assertSame(240, SlaKpis::geral((string) $this->tenant->id, 30)['resolution']['tempo_medio']);
    }

    public function test_periodo_e_isolamento_entre_empresas(): void
    {
        $this->concluida($this->ana, 60, 60 * 24 * 40); // 40 dias atrás: fora dos 30 dias
        $this->concluida($this->ana, 60, 600);

        $outra = Tenant::create(['name' => 'Outra '.uniqid(), 'slug' => 'f4o-'.uniqid(), 'plan_id' => $this->tenant->plan_id, 'status' => 'active']);

        $this->assertSame(1, SlaKpis::geral((string) $this->tenant->id, 30)['resolution']['total']);
        $this->assertSame(2, SlaKpis::geral((string) $this->tenant->id, 90)['resolution']['total']);
        $this->assertSame(0, SlaKpis::geral((string) $outra->id, 90)['resolution']['total']);
    }

    public function test_painel_mostra_as_quebras_muda_o_periodo_e_exporta_csv(): void
    {
        $this->concluida($this->ana, 60);
        $this->concluida($this->beto, 150, 60 * 24 * 20); // 20 dias atrás: entra em 30, fora de 7
        $this->aberta(300);

        $tela = Livewire::test(PainelSlaEmergencia::class)
            ->assertSee('Por técnico')->assertSee('Por cliente')->assertSee('Por prioridade')->assertSee('Ana Técnica')->assertSee('Beto Técnico')->assertSee('Vencidos agora');

        $tela->set('dias', 7)->assertSee('últimos 7 dias')->assertDontSee('Beto Técnico');

        $download = Livewire::test(PainelSlaEmergencia::class)->callAction('exportar');
        $download->assertFileDownloaded('indicadores-sla-2026-11-09.csv');
    }

    public function test_detalhe_para_exportar_traz_uma_linha_por_cronometro_com_situacao(): void
    {
        $this->concluida($this->ana, 150);
        $linhas = SlaKpis::detalhe((string) $this->tenant->id, 30);

        $res = $linhas->firstWhere('relogio', 'Resolução');
        $this->assertSame('Estourado', $res['situacao']);
        $this->assertSame('Ana Técnica', $res['tecnico']);
        $this->assertSame(150, $res['gasto_min']);
        $this->assertSame(100, $res['meta_min']);
    }

    public function test_gestao_a_vista_conta_atrasada_pelo_relogio_de_sla_e_mantem_a_regra_antiga(): void
    {
        $porRelogio = $this->aberta(300);        // política alta: resolução 100 min, já vencida; sem sla_target_minutes
        $legado = $this->aberta(300, 'baixa');   // sem política; prazo antigo digitado
        $legado->update(['sla_target_minutes' => 60]);
        $this->aberta(10);                       // no prazo

        $r = (new GestaoAVistaService((string) $this->tenant->id))->resumoOs(['from' => null, 'until' => null, 'branchId' => null, 'assetId' => null]);

        $this->assertSame(2, $r['atrasadas']);
        $this->assertNotNull($porRelogio->id);
    }

    public function test_bloco_atendidos_no_prazo_usa_a_resolucao_e_cai_na_regra_antiga_sem_cronometro(): void
    {
        $this->concluida($this->ana, 60);   // resolução no prazo
        $this->concluida($this->ana, 150);  // estourou

        $p = (new AtendimentosNoPrazo)->data(['meta' => 90, 'dias' => 30]);
        $this->assertSame('50%', $p['value']);

        $legado = $this->concluida($this->ana, 30, 600, null, 'Preventiva', null, 'baixa'); // sem política: não tem cronômetro de resolução
        $legado->forceFill(['sla_target_minutes' => 60])->saveQuietly();
        $this->assertSame('67%', (new AtendimentosNoPrazo)->data(['meta' => 90, 'dias' => 30])['value']);
    }

    public function test_blocos_novos_estao_no_catalogo_e_calculam(): void
    {
        foreach ([SlaResolucaoCumprida::class, SlaVencidosAgora::class, SlaTempoMedioResolucao::class] as $classe) {
            $this->assertSame($classe, BlockRegistry::all()[$classe::key()]);
        }

        $this->assertSame('—', (new SlaResolucaoCumprida)->data(['meta' => 90, 'dias' => 30])['value']);

        $this->concluida($this->ana, 60);
        $this->concluida($this->ana, 80);
        $this->aberta(300);

        $this->assertSame('100%', (new SlaResolucaoCumprida)->data(['meta' => 90, 'dias' => 30])['value']);
        $this->assertSame('1h 10min', (new SlaTempoMedioResolucao)->data(['dias' => 30])['value']);
        $this->assertSame(1, (new SlaVencidosAgora)->data([])['value']);
    }
}
