<?php

namespace Tests\Feature;

use App\Filament\Pages\PainelSlaEmergencia;
use App\Filament\Resources\SlaPolicyResource\Pages\CreateSlaPolicy;
use App\Filament\Resources\SlaPolicyResource\Pages\ListSlaPolicies;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\SlaTimer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Prioridade\PrioridadeDaOs;
use App\Services\Sla\SlaService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** F2: cronômetros de SLA da OS (Resposta, Atendimento, Resolução), pausa por status, recálculo por prioridade, legado e painel. */
class F2SlaTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        SlaService::esquecer();
        Carbon::setTestNow('2026-11-09 08:00:00');
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets' => true, 'tabela_maintenance_orders' => true, 'tabela_sla_policies' => true]]);
        $this->tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'f2-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $this->admin = User::create(['name' => 'Ana', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        $this->admin->forceFill(['email_verified_at' => now()])->save();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]));
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function politica(string $prioridade, ?string $tipo, ?int $resposta, ?int $atendimento, ?int $resolucao): SlaPolicy
    {
        return SlaPolicy::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'priority_code' => $prioridade, 'maintenance_type' => $tipo, 'response_minutes' => $resposta, 'service_minutes' => $atendimento, 'resolution_minutes' => $resolucao, 'is_active' => true]);
    }

    private function os(array $extra = []): MaintenanceOrder
    {
        $ativo = Asset::create(['tenant_id' => $this->tenant->id, 'name' => 'Gerador', 'tag' => 'AST-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL]);

        return MaintenanceOrder::create($extra + ['tenant_id' => $this->tenant->id, 'asset_id' => $ativo->id, 'description' => 'x', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'status' => 'Pendente', 'priority' => 'alta']);
    }

    private function timer(MaintenanceOrder $os, string $relogio): ?SlaTimer
    {
        return SlaTimer::withoutGlobalScopes()->where('subject_id', $os->id)->where('clock', $relogio)->first();
    }

    public function test_os_nasce_com_um_cronometro_por_relogio_da_politica(): void
    {
        $this->politica('alta', null, 60, 240, 1440);
        $os = $this->os();

        $this->assertSame(['response', 'service', 'resolution'], SlaTimer::withoutGlobalScopes()->where('subject_id', $os->id)->orderBy('target_minutes')->pluck('clock')->all());
        $this->assertSame('2026-11-09 12:00:00', $this->timer($os, 'service')->due_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-10 08:00:00', $this->timer($os, 'resolution')->due_at->format('Y-m-d H:i:s'));
    }

    public function test_politica_do_tipo_vence_a_geral_e_sem_politica_nao_ha_cronometro(): void
    {
        $this->politica('alta', null, null, 240, null);
        $this->politica('alta', MaintenanceOrder::TYPE_CORRECTIVE, null, 30, null);
        $this->politica('baixa', null, null, 999, null);

        $this->assertSame(30, $this->timer($this->os(), 'service')->target_minutes);
        $this->assertSame(0, SlaTimer::withoutGlobalScopes()->where('subject_id', $this->os(['priority' => 'media'])->id)->count());
    }

    public function test_legado_sla_target_minutes_vira_o_relogio_atendimento(): void
    {
        $os = $this->os(['priority' => 'media', 'sla_target_minutes' => 60]);

        $this->assertSame(60, $this->timer($os, 'service')->target_minutes);
        $this->assertNull($this->timer($os, 'service')->policy_id);

        $os->update(['sla_target_minutes' => 120]);
        $this->assertSame(120, $this->timer($os, 'service')->fresh()->target_minutes);
    }

    public function test_eventos_da_os_param_os_relogios_com_resultado(): void
    {
        $this->politica('alta', null, 60, 240, 1440);
        $os = $this->os();

        Carbon::setTestNow('2026-11-09 08:30:00');
        $os->update(['technician_id' => $this->admin->id]);
        $this->assertSame('met', $this->timer($os, 'response')->result);
        $this->assertNull($this->timer($os, 'service')->stopped_at);

        Carbon::setTestNow('2026-11-09 13:00:00'); // passou das 4h do Atendimento
        $os->update(['status' => 'Em Andamento']);
        $this->assertSame('breached', $this->timer($os, 'service')->result);

        Carbon::setTestNow('2026-11-09 18:00:00');
        $os->update(['status' => 'Concluída']);
        $this->assertSame('met', $this->timer($os, 'resolution')->result);
    }

    public function test_status_de_espera_pausa_e_o_prazo_anda_junto(): void
    {
        $this->politica('alta', null, null, null, 240);
        $os = $this->os();

        Carbon::setTestNow('2026-11-09 09:00:00');
        $os->update(['internal_status' => 'aguardando_peca']);
        $this->assertNotNull($this->timer($os, 'resolution')->paused_at);
        $this->assertSame('pausado', $this->timer($os, 'resolution')->estado());

        Carbon::setTestNow('2026-11-09 11:00:00'); // 2h parado
        $os->update(['internal_status' => 'em_manutencao']);
        $t = $this->timer($os, 'resolution');
        $this->assertNull($t->paused_at);
        $this->assertSame(7200, $t->paused_seconds);
        $this->assertSame('2026-11-09 14:00:00', $t->due_at->format('Y-m-d H:i:s'), '08:00 + 4h + 2h de pausa');
    }

    public function test_cancelar_a_os_encerra_os_relogios_como_cancelados(): void
    {
        $this->politica('alta', null, 60, null, 1440);
        $os = $this->os();
        $os->update(['status' => 'Cancelada']);

        $this->assertSame(['cancelled', 'cancelled'], SlaTimer::withoutGlobalScopes()->where('subject_id', $os->id)->pluck('result')->all());
        $this->assertSame('cancelado', $this->timer($os, 'resolution')->estado());
    }

    public function test_estados_no_prazo_em_risco_e_vencido(): void
    {
        $this->politica('alta', null, null, null, 100);
        $t = $this->timer($this->os(), 'resolution');

        $this->assertSame('no_prazo', $t->estado(70));
        Carbon::setTestNow('2026-11-09 09:15:00'); // 75 de 100 min
        $this->assertSame('em_risco', $t->estado(70));
        $this->assertSame(25, $t->minutosRestantes());
        Carbon::setTestNow('2026-11-09 09:41:00');
        $this->assertSame('vencido', $t->estado(70));
    }

    public function test_mudar_a_prioridade_refaz_os_prazos_abertos_mas_nao_os_fechados(): void
    {
        $this->politica('alta', null, 60, null, 240);
        $this->politica('critica', null, 15, null, 120);
        $os = $this->os();
        $os->update(['technician_id' => $this->admin->id]); // fecha a Resposta

        PrioridadeDaOs::alterar($os->fresh(), 'critica', 'Cliente parou a obra');

        $this->assertSame(120, $this->timer($os, 'resolution')->target_minutes);
        $this->assertSame('2026-11-09 10:00:00', $this->timer($os, 'resolution')->due_at->format('Y-m-d H:i:s'));
        $this->assertSame(60, $this->timer($os, 'response')->target_minutes, 'resposta já fechada não muda');
    }

    public function test_sla_color_usa_o_pior_relogio_aberto_e_o_legado_continua_funcionando(): void
    {
        $this->politica('alta', null, null, 60, 1440);
        $os = $this->os();
        $this->assertSame('success', $os->fresh()->slaColor());

        Carbon::setTestNow('2026-11-09 09:30:00');
        $this->assertSame('danger', $os->fresh()->slaColor());

        $semPolitica = $this->os(['priority' => 'baixa']);
        $this->assertNull($semPolitica->fresh()->slaColor());
    }

    public function test_comando_migra_legado_so_com_apply_e_nao_duplica(): void
    {
        $os = $this->os(['priority' => 'media']);
        $os->update(['sla_target_minutes' => 90]);
        SlaTimer::withoutGlobalScopes()->where('subject_id', $os->id)->delete(); // simula OS anterior ao F2

        $this->artisan('sla:migrar-legado')->assertSuccessful();
        $this->assertNull($this->timer($os, 'service'));

        $this->artisan('sla:migrar-legado', ['--apply' => true])->assertSuccessful();
        $this->assertSame(90, $this->timer($os, 'service')->target_minutes);

        $this->artisan('sla:migrar-legado', ['--apply' => true])->assertSuccessful();
        $this->assertSame(1, SlaTimer::withoutGlobalScopes()->where('subject_id', $os->id)->count());
    }

    public function test_resumo_calcula_percentual_cumprido_e_abertos_por_estado(): void
    {
        $this->politica('alta', null, null, null, 100);
        $a = $this->os();
        $b = $this->os();
        $this->os(); // continua aberta

        Carbon::setTestNow('2026-11-09 09:00:00');
        $a->update(['status' => 'Concluída']); // 60 min: cumpriu
        Carbon::setTestNow('2026-11-09 10:00:00');
        $b->update(['status' => 'Concluída']); // 120 min: estourou

        $resumo = SlaService::resumo((string) $this->tenant->id);
        $this->assertSame(50, $resumo['relogios']['resolution']['pct']);
        $this->assertSame(2, $resumo['relogios']['resolution']['total']);
        $this->assertSame(1, $resumo['abertos']['vencido']);
    }

    public function test_cadastro_de_politica_valida_duplicidade_e_painel_abre(): void
    {
        $this->politica('alta', null, 60, null, null);

        Livewire::test(CreateSlaPolicy::class)
            ->fillForm(['priority_code' => 'alta', 'maintenance_type' => null, 'service_minutes' => 30, 'is_active' => true])
            ->call('create')->assertHasFormErrors(['priority_code']);

        Livewire::test(CreateSlaPolicy::class)
            ->fillForm(['priority_code' => 'media', 'maintenance_type' => null, 'is_active' => true])
            ->call('create')->assertHasFormErrors(['response_minutes']);

        Livewire::test(CreateSlaPolicy::class)
            ->fillForm(['priority_code' => 'media', 'maintenance_type' => null, 'resolution_minutes' => 2880, 'is_active' => true])
            ->call('create')->assertHasNoFormErrors();

        Livewire::test(ListSlaPolicies::class)->assertSee('48h')->callAction('configurar', ['pause_statuses' => ['aguardando_peca'], 'warn_pct' => 80]);
        SlaService::esquecer();
        $this->assertSame(80, SlaService::settings((string) $this->tenant->id)->warn_pct);

        $this->os();
        Livewire::test(PainelSlaEmergencia::class)->assertSee('Cumprimento do SLA')->assertSee('Resposta: No prazo');
    }
}
