<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceOrderResource;
use App\Filament\Resources\SlaHolidayResource\Pages\CreateSlaHoliday;
use App\Filament\Resources\SlaHolidayResource\Pages\ListSlaHolidays;
use App\Filament\Resources\SlaPolicyResource\Pages\ListSlaPolicies;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SlaHoliday;
use App\Models\SlaPolicy;
use App\Models\SlaTimer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Prioridade\PrioridadeDaOs;
use App\Services\Sla\SlaCalendar;
use App\Services\Sla\SlaKpis;
use App\Services\Sla\SlaService;
use App\Support\FeriadosBr;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** F5: calendário de SLA (horário comercial, fuso e feriados): prazo e tempo gasto em minutos de expediente. */
class F5SlaCalendarioTest extends TestCase
{
    use DatabaseTransactions;

    private const SP = 'America/Sao_Paulo';

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        SlaService::esquecer();
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets' => true, 'tabela_maintenance_orders' => true, 'tabela_sla_policies' => true, 'tabela_sla_holidays' => true]]);
        $this->tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'f5-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
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

    private function sp(string $quando): Carbon
    {
        return Carbon::parse($quando, self::SP);
    }

    private function comercial(?array $horarios = null, array $feriados = []): void
    {
        SlaService::salvarSettings((string) $this->tenant->id, SlaService::PAUSA_PADRAO, 70, 150, ['modo' => 'comercial', 'timezone' => self::SP, 'horarios' => $horarios]);
        foreach ($feriados as $dia) {
            SlaHoliday::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'date' => $dia, 'name' => 'Feriado '.$dia]);
        }
        SlaService::esquecer();
    }

    private function cal(): SlaCalendar
    {
        return SlaCalendar::doTenant((string) $this->tenant->id);
    }

    private function politica(int $resolucao, string $prio = 'alta'): void
    {
        SlaPolicy::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'priority_code' => $prio, 'resolution_minutes' => $resolucao, 'is_active' => true]);
    }

    private function os(string $criadaEm): MaintenanceOrder
    {
        $ativo = Asset::create(['tenant_id' => $this->tenant->id, 'name' => 'Gerador', 'tag' => 'AST-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL]);
        $o = new MaintenanceOrder;
        $o->forceFill(['tenant_id' => $this->tenant->id, 'asset_id' => $ativo->id, 'description' => 'x', 'maintenance_type' => 'Preventiva', 'status' => 'Pendente', 'priority' => 'alta',
            'created_at' => $this->sp($criadaEm), 'updated_at' => $this->sp($criadaEm)]);
        Carbon::setTestNow($this->sp($criadaEm));
        $o->save();

        return $o;
    }

    private function resolucao(MaintenanceOrder $os): SlaTimer
    {
        return SlaTimer::withoutGlobalScopes()->where('subject_id', $os->id)->where('clock', 'resolution')->firstOrFail()->refresh();
    }

    private function fmt(Carbon $c): string
    {
        return $c->copy()->setTimezone(self::SP)->format('D Y-m-d H:i');
    }

    public function test_somar_pula_noite_fim_de_semana_e_feriado(): void
    {
        $this->comercial(null, ['2026-11-20']);

        $this->assertSame('Mon 2026-11-09 14:00', $this->fmt($this->cal()->somar($this->sp('2026-11-06 16:00'), 480)), 'sexta 16h + 8h úteis');
        $this->assertSame('Mon 2026-11-09 09:00', $this->fmt($this->cal()->somar($this->sp('2026-11-06 20:00'), 60)), 'começa fora do expediente');
        $this->assertSame('Mon 2026-11-23 10:00', $this->fmt($this->cal()->somar($this->sp('2026-11-19 17:00'), 180)), 'quinta 17h + 3h com a sexta feriado');
    }

    public function test_entre_conta_so_minutos_de_expediente_e_janela_com_almoco(): void
    {
        $this->comercial();
        $this->assertSame(240.0, $this->cal()->entre($this->sp('2026-11-06 16:00'), $this->sp('2026-11-09 10:00')));
        $this->assertSame(0.0, $this->cal()->entre($this->sp('2026-11-07 09:00'), $this->sp('2026-11-08 20:00')), 'fim de semana inteiro');

        $this->comercial(['1' => [['08:00', '12:00'], ['13:00', '18:00']]]);
        $this->assertSame('Mon 2026-11-09 14:00', $this->fmt($this->cal()->somar($this->sp('2026-11-09 11:00'), 120)), 'o almoço não conta');
        $this->assertSame(120.0, $this->cal()->entre($this->sp('2026-11-09 11:00'), $this->sp('2026-11-09 14:00')));
    }

    public function test_vinte_e_quatro_por_sete_continua_igual(): void
    {
        $cal = SlaCalendar::vinte4x7();
        $this->assertSame('Sat 2026-11-07 04:00', $this->fmt($cal->somar($this->sp('2026-11-06 20:00'), 480)));
        $this->assertSame(480.0, $cal->entre($this->sp('2026-11-06 20:00'), $this->sp('2026-11-07 04:00')));
    }

    public function test_cronometro_comercial_calcula_prazo_estado_e_restante_em_minutos_de_expediente(): void
    {
        $this->comercial();
        $this->politica(480);
        $os = $this->os('2026-11-06 16:00'); // sexta
        $t = $this->resolucao($os);

        $this->assertSame('comercial', $t->calendar_mode);
        $this->assertSame('Mon 2026-11-09 14:00', $this->fmt($t->due_at));

        Carbon::setTestNow($this->sp('2026-11-07 12:00')); // sábado
        $this->assertSame('no_prazo', $t->estado(70));
        $this->assertSame(120.0, $t->minutosGastos());

        Carbon::setTestNow($this->sp('2026-11-09 13:00'));
        $this->assertSame('em_risco', $t->estado(70)); // 420 de 480
        $this->assertSame(60, $t->minutosRestantes());

        Carbon::setTestNow($this->sp('2026-11-09 14:01'));
        $this->assertSame('vencido', $t->estado(70));
    }

    public function test_ao_parar_grava_os_minutos_de_expediente_e_o_tempo_medio_usa_eles(): void
    {
        $this->comercial();
        $this->politica(480);
        $os = $this->os('2026-11-06 16:00');

        Carbon::setTestNow($this->sp('2026-11-09 11:00'));
        $os->update(['status' => 'Concluída']);

        $t = $this->resolucao($os);
        $this->assertSame(300, $t->spent_minutes, '2h da sexta + 3h da segunda');
        $this->assertSame('met', $t->result);
        $this->assertSame(300, SlaKpis::geral((string) $this->tenant->id, 30)['resolution']['tempo_medio']);
    }

    public function test_pausa_no_horario_comercial_empurra_o_prazo_pelo_tempo_de_expediente_parado(): void
    {
        $this->comercial();
        $this->politica(240);
        $os = $this->os('2026-11-09 09:00'); // segunda; prazo original 13:00

        Carbon::setTestNow($this->sp('2026-11-09 10:00'));
        $os->update(['internal_status' => 'aguardando_peca']);
        Carbon::setTestNow($this->sp('2026-11-10 09:00'));
        $os->update(['internal_status' => 'em_manutencao']);

        $t = $this->resolucao($os);
        $this->assertSame(540 * 60, $t->paused_seconds, 'segunda 10h-18h (480) + terça 8h-9h (60)');
        $this->assertSame('Tue 2026-11-10 12:00', $this->fmt($t->due_at));
    }

    public function test_mudar_o_calendario_nao_reescreve_cronometros_em_andamento(): void
    {
        $this->politica(480);
        $antiga = $this->os('2026-11-06 16:00');
        $this->assertSame('24x7', $this->resolucao($antiga)->calendar_mode);
        $prazoAntigo = $this->resolucao($antiga)->due_at->copy();

        $this->comercial();
        $nova = $this->os('2026-11-06 16:00');

        $this->assertSame('24x7', $this->resolucao($antiga)->calendar_mode);
        $this->assertTrue($prazoAntigo->equalTo($this->resolucao($antiga)->due_at));
        $this->assertSame('comercial', $this->resolucao($nova)->calendar_mode);
    }

    public function test_mudar_a_prioridade_recalcula_o_prazo_no_calendario_comercial(): void
    {
        $this->comercial();
        $this->politica(480);
        $this->politica(120, 'critica');
        $os = $this->os('2026-11-06 16:00');

        Carbon::setTestNow($this->sp('2026-11-06 16:30'));
        PrioridadeDaOs::alterar($os->fresh(), 'critica', 'Cliente parou a obra');

        $this->assertSame('Fri 2026-11-06 18:00', $this->fmt($this->resolucao($os)->due_at), 'sexta 16h + 2h úteis termina às 18h, no fim do expediente');
    }

    public function test_filtro_em_risco_considera_o_calendario_do_cronometro(): void
    {
        $this->comercial();
        $this->politica(480);
        $os = $this->os('2026-11-06 16:00');

        Carbon::setTestNow($this->sp('2026-11-07 12:00')); // sábado: 25% do prazo, não está em risco
        $this->assertSame([], MaintenanceOrderResource::osComRelogioEmRisco());

        Carbon::setTestNow($this->sp('2026-11-09 13:00')); // 87%
        $this->assertSame([$os->id], MaintenanceOrderResource::osComRelogioEmRisco());
    }

    public function test_feriados_nacionais_incluem_pascoa_e_respeitam_o_ano(): void
    {
        $n26 = FeriadosBr::nacionais(2026);
        $this->assertSame('Sexta-feira Santa', $n26['2026-04-03']);
        $this->assertSame('Tiradentes', $n26['2026-04-21']);
        $this->assertSame('Consciência Negra', $n26['2026-11-20']);
        $this->assertArrayNotHasKey('2026-02-16', $n26);

        $this->assertArrayHasKey('2026-02-16', FeriadosBr::nacionais(2026, true), 'Carnaval (segunda)');
        $this->assertArrayHasKey('2026-06-04', FeriadosBr::nacionais(2026, true), 'Corpus Christi');
        $this->assertArrayHasKey('2027-03-26', FeriadosBr::nacionais(2027));
        $this->assertArrayNotHasKey('2023-11-20', FeriadosBr::nacionais(2023), 'Consciência Negra só é nacional desde 2024');
    }

    public function test_configuracao_salva_calendario_e_valida_o_horario(): void
    {
        $dados = ['pause_statuses' => ['aguardando_peca'], 'warn_pct' => 70, 'escalate_pct' => 150, 'calendar_mode' => 'comercial', 'timezone' => 'America/Manaus',
            'dia_1' => true, 'ini_1' => '07:30', 'fim_1' => '17:30', 'dia_2' => false, 'dia_3' => false, 'dia_4' => false, 'dia_5' => false, 'dia_6' => false, 'dia_7' => false,
            'ini_2' => '08:00', 'fim_2' => '18:00', 'ini_3' => '08:00', 'fim_3' => '18:00', 'ini_4' => '08:00', 'fim_4' => '18:00', 'ini_5' => '08:00', 'fim_5' => '18:00',
            'ini_6' => '08:00', 'fim_6' => '12:00', 'ini_7' => '08:00', 'fim_7' => '12:00'];

        Livewire::test(ListSlaPolicies::class)->callAction('configurar', ['fim_1' => '07:00'] + $dados)->assertHasActionErrors(['fim_1']);

        Livewire::test(ListSlaPolicies::class)->callAction('configurar', $dados)->assertHasNoActionErrors();
        SlaService::esquecer();

        $s = SlaService::settings((string) $this->tenant->id);
        $this->assertSame('comercial', $s->calendar_mode);
        $this->assertSame('America/Manaus', $s->timezone);
        $this->assertSame(['1' => ['07:30', '17:30']], $s->business_hours);
        $this->assertTrue($this->cal()->ehComercial());
    }

    public function test_cadastro_de_feriados_importa_sem_duplicar_e_valida_data_repetida(): void
    {
        Livewire::test(ListSlaHolidays::class)->callAction('importar', ['ano' => 2026, 'facultativos' => false])->assertHasNoActionErrors();
        $total = SlaHoliday::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count();
        $this->assertSame(count(FeriadosBr::nacionais(2026)), $total);

        Livewire::test(ListSlaHolidays::class)->callAction('importar', ['ano' => 2026, 'facultativos' => true])->assertHasNoActionErrors();
        $this->assertSame(count(FeriadosBr::nacionais(2026, true)), SlaHoliday::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        Livewire::test(CreateSlaHoliday::class)->fillForm(['date' => '2026-12-25', 'name' => 'Repetido'])->call('create')->assertHasFormErrors(['date']);
        Livewire::test(CreateSlaHoliday::class)->fillForm(['date' => '2026-08-15', 'name' => 'Aniversário da cidade'])->call('create')->assertHasNoFormErrors();
    }
}
