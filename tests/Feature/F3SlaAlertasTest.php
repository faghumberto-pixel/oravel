<?php

namespace Tests\Feature;

use App\Filament\Pages\TechnicianDailyTasks;
use App\Livewire\MaintenanceOrderFieldWizard;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\SlaTimer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Prioridade\PrioridadeDaOs;
use App\Services\Sla\SlaAlertas;
use App\Services\Sla\SlaService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** F3: avisos de SLA (em risco, vencido, escalonado) sem repetir, e o SLA no app do técnico (contagem e ordenação por prazo). */
class F3SlaAlertasTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $admin;

    private User $tecnico;

    protected function setUp(): void
    {
        parent::setUp();
        SlaService::esquecer();
        Carbon::setTestNow('2026-11-09 08:00:00');
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets' => true, 'tabela_maintenance_orders' => true, 'tabela_sla_policies' => true]]);
        $this->tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'f3-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $this->admin = User::create(['name' => 'Ana', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        $this->admin->forceFill(['email_verified_at' => now()])->save();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]));
        $this->tecnico = User::create(['name' => 'Tecnico', 'email' => 't-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        $this->tecnico->forceFill(['email_verified_at' => now()])->save();
        $this->tecnico->givePermissionTo(Permission::firstOrCreate(['name' => 'ler_ordem_servico', 'guard_name' => 'web']));
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function politica(int $resolucao = 100, ?int $atendimento = null): void
    {
        SlaPolicy::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'priority_code' => 'alta', 'service_minutes' => $atendimento, 'resolution_minutes' => $resolucao, 'is_active' => true]);
    }

    private function os(array $extra = []): MaintenanceOrder
    {
        $ativo = Asset::create(['tenant_id' => $this->tenant->id, 'name' => 'Gerador', 'tag' => 'AST-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL]);

        return MaintenanceOrder::create($extra + ['tenant_id' => $this->tenant->id, 'asset_id' => $ativo->id, 'description' => 'x', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'status' => 'Pendente', 'priority' => 'alta']);
    }

    private function avisos(User $u, string $trecho): int
    {
        return $u->fresh()->notifications->filter(fn ($n) => str_contains(json_encode($n->data, JSON_UNESCAPED_UNICODE), $trecho))->count();
    }

    public function test_em_risco_avisa_a_gerencia_e_o_tecnico_uma_vez_so(): void
    {
        $this->politica(100);
        $this->os(['technician_id' => $this->tecnico->id]);

        $this->assertSame(['em_risco' => 0, 'vencido' => 0, 'escalonado' => 0], SlaAlertas::avaliar());

        Carbon::setTestNow('2026-11-09 09:15:00'); // 75% do prazo
        $this->assertSame(1, SlaAlertas::avaliar()['em_risco']);
        $this->assertSame(0, SlaAlertas::avaliar()['em_risco'], 'não repete');

        $this->assertSame(1, $this->avisos($this->admin, 'SLA em risco'));
        $this->assertSame(1, $this->avisos($this->tecnico, 'SLA em risco'));
    }

    public function test_vencido_avisa_uma_vez_e_o_escalonamento_so_depois_de_150_por_cento(): void
    {
        $this->politica(100);
        $this->os(['technician_id' => $this->tecnico->id]);

        Carbon::setTestNow('2026-11-09 10:00:00'); // 120 min = 120%
        $r = SlaAlertas::avaliar();
        $this->assertSame(1, $r['vencido']);
        $this->assertSame(0, $r['escalonado']);
        $this->assertSame(0, SlaAlertas::avaliar()['vencido']);

        Carbon::setTestNow('2026-11-09 10:40:00'); // 160 min = 160%
        $this->assertSame(1, SlaAlertas::avaliar()['escalonado']);
        $this->assertSame(0, SlaAlertas::avaliar()['escalonado']);

        $this->assertSame(1, $this->avisos($this->admin, 'SLA vencido'));
        $this->assertSame(1, $this->avisos($this->admin, 'SLA escalonado'));
        $this->assertSame(1, $this->avisos($this->tecnico, 'SLA vencido'));
        $this->assertSame(0, $this->avisos($this->tecnico, 'SLA escalonado'), 'escalonamento é para a gerência');
    }

    public function test_percentual_de_escalonamento_do_tenant_e_respeitado(): void
    {
        $this->politica(100);
        SlaService::salvarSettings((string) $this->tenant->id, SlaService::PAUSA_PADRAO, 70, 300);
        $this->os();

        Carbon::setTestNow('2026-11-09 10:40:00'); // 160%: abaixo de 300%
        $this->assertSame(0, SlaAlertas::avaliar()['escalonado']);
        Carbon::setTestNow('2026-11-09 13:00:00'); // 300%
        $this->assertSame(1, SlaAlertas::avaliar()['escalonado']);
    }

    public function test_relogio_pausado_ou_parado_nao_gera_aviso(): void
    {
        $this->politica(100);
        $pausada = $this->os();
        $concluida = $this->os();

        $pausada->update(['internal_status' => 'aguardando_peca']);
        $concluida->update(['status' => 'Concluída']);

        Carbon::setTestNow('2026-11-09 12:00:00');
        $this->assertSame(['em_risco' => 0, 'vencido' => 0, 'escalonado' => 0], SlaAlertas::avaliar());
    }

    public function test_mudar_a_prioridade_zera_as_marcas_e_permite_novo_aviso(): void
    {
        $this->politica(100);
        SlaPolicy::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'priority_code' => 'critica', 'resolution_minutes' => 400, 'is_active' => true]);
        $os = $this->os();

        Carbon::setTestNow('2026-11-09 09:30:00');
        $this->assertSame(1, SlaAlertas::avaliar()['em_risco']);

        PrioridadeDaOs::alterar($os->fresh(), 'critica', 'Cliente parou a obra'); // prazo vira 400 min: volta a "no prazo"
        $this->assertSame(0, SlaAlertas::avaliar()['em_risco']);

        Carbon::setTestNow('2026-11-09 13:00:00'); // 300 de 400 = 75%
        $this->assertSame(1, SlaAlertas::avaliar()['em_risco']);
    }

    public function test_varios_relogios_da_mesma_os_geram_um_aviso_so_por_evento(): void
    {
        $this->politica(100, 60);
        $this->os(['technician_id' => $this->tecnico->id]);

        Carbon::setTestNow('2026-11-09 10:00:00'); // Atendimento 60 e Resolução 100: os dois vencidos
        $r = SlaAlertas::avaliar();

        $this->assertSame(2, $r['vencido'], 'dois relógios sinalizados');
        $this->assertSame(1, $this->avisos($this->admin, 'SLA vencido'), 'mas um aviso só');
        $this->assertSame(1, $this->avisos($this->tecnico, 'SLA vencido'));
        $this->assertSame(0, SlaAlertas::avaliar()['vencido']);
    }

    public function test_comando_esta_agendado_a_cada_cinco_minutos_e_roda(): void
    {
        $this->artisan('sla:avaliar')->assertSuccessful();

        $evento = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'sla:avaliar'));
        $this->assertNotNull($evento);
        $this->assertSame('*/5 * * * *', $evento->expression);
    }

    public function test_agenda_do_tecnico_mostra_o_sla_e_ordena_por_prazo(): void
    {
        $this->politica(100); // prioridade alta: 100 min
        $this->os(['technician_id' => $this->tecnico->id, 'description' => 'Sem pressa']); // vence em 100 min
        Carbon::setTestNow('2026-11-09 08:50:00');
        $urgente = $this->os(['technician_id' => $this->tecnico->id, 'description' => 'Urgente']); // vence mais tarde, 09:30
        $this->actingAs($this->tecnico);

        Carbon::setTestNow('2026-11-09 09:20:00');
        $tela = Livewire::test(TechnicianDailyTasks::class)->set('sortBy', 'sla');
        $tarefas = $tela->instance()->technicianTasks->values();

        $this->assertSame(2, $tarefas->count());
        $this->assertSame('em_risco', $tarefas[0]['sla']['estado']); // a mais antiga (vence 09:40) vem primeiro
        $tela->assertSee('Resolução ·');
        $this->assertTrue($tarefas[0]['sla']['prazo']->lessThan($tarefas[1]['sla']['prazo']));
        $this->assertNotNull($urgente->id);
    }

    public function test_assistente_de_campo_mostra_o_relogio_mais_critico(): void
    {
        $this->politica(100, 60);
        $os = $this->os(['technician_id' => $this->tecnico->id]);

        Carbon::setTestNow('2026-11-09 08:50:00');
        $this->actingAs($this->tecnico);
        $r = Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $os])->instance()->slaRemaining;

        $this->assertSame('Atendimento', $r['label']);
        $this->assertSame(['hours' => 0, 'minutes' => 10, 'exceeded' => false, 'paused' => false], array_intersect_key($r, array_flip(['hours', 'minutes', 'exceeded', 'paused'])));

        Carbon::setTestNow('2026-11-09 09:10:00');
        $this->assertTrue(Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $os])->instance()->slaRemaining['exceeded']);
    }

    public function test_resumo_da_os_e_nulo_sem_cronometro_aberto(): void
    {
        $this->assertNull(SlaService::resumoDaOs($this->os(['priority' => 'baixa'])->load('slaTimers')));
        $this->assertSame(0, SlaTimer::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }
}
