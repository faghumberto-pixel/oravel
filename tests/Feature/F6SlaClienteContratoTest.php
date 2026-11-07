<?php

namespace Tests\Feature;

use App\Filament\Resources\SlaPolicyResource\Pages\CreateSlaPolicy;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\SlaTimer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Sla\SlaService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** F6: política de SLA por cliente e por contrato (a mais específica vence) e prazos de SLA no pacote offline do app do colaborador. */
class F6SlaClienteContratoTest extends TestCase
{
    use DatabaseTransactions;

    private const CELULAR = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        SlaService::esquecer();
        Carbon::setTestNow('2026-11-09 12:00:00');
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets' => true, 'tabela_maintenance_orders' => true, 'tabela_sla_policies' => true, 'modulo_app_colaborador' => true]]);
        $this->tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'f6-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
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

    private function politica(int $resolucao, array $escopo = []): SlaPolicy
    {
        return SlaPolicy::withoutGlobalScopes()->create($escopo + ['tenant_id' => $this->tenant->id, 'priority_code' => 'alta', 'resolution_minutes' => $resolucao, 'is_active' => true]);
    }

    private function cliente(string $nome): Client
    {
        return Client::create(['tenant_id' => $this->tenant->id, 'name' => $nome]);
    }

    private function contrato(Client $cliente, Asset $ativo): Contract
    {
        return Contract::create(['tenant_id' => $this->tenant->id, 'client_id' => $cliente->id, 'asset_id' => $ativo->id, 'contract_number' => 'CT-'.uniqid(), 'start_date' => now(), 'price' => 100, 'status' => 'Ativo']);
    }

    private function ativo(): Asset
    {
        return Asset::create(['tenant_id' => $this->tenant->id, 'name' => 'Gerador '.uniqid(), 'patrimonio' => 'PAT-'.random_int(1000, 9999), 'status' => Asset::STATUS_DISPONIVEL]);
    }

    private function os(?Asset $ativo = null, ?Client $cliente = null, array $extra = []): MaintenanceOrder
    {
        return MaintenanceOrder::create($extra + ['tenant_id' => $this->tenant->id, 'asset_id' => ($ativo ?? $this->ativo())->id, 'client_id' => $cliente?->id, 'description' => 'x',
            'maintenance_type' => 'Corretiva', 'status' => 'Pendente', 'priority' => 'alta']);
    }

    private function meta(MaintenanceOrder $os): int
    {
        return (int) SlaTimer::withoutGlobalScopes()->where('subject_id', $os->id)->where('clock', 'resolution')->value('target_minutes');
    }

    public function test_politica_do_cliente_vale_so_para_ele_e_vence_a_geral(): void
    {
        $norte = $this->cliente('Construtora Norte');
        $sul = $this->cliente('Construtora Sul');
        $this->politica(1440);
        $this->politica(240, ['client_id' => $norte->id]);

        $this->assertSame(240, $this->meta($this->os(null, $norte)));
        $this->assertSame(1440, $this->meta($this->os(null, $sul)));
        $this->assertSame(1440, $this->meta($this->os()), 'OS sem cliente fica na geral');
    }

    public function test_politica_do_contrato_vence_a_do_cliente_e_o_cliente_vem_do_contrato(): void
    {
        $norte = $this->cliente('Construtora Norte');
        $this->politica(1440);
        $this->politica(480, ['client_id' => $norte->id]);

        $ativoDoContrato = $this->ativo();
        $contrato = $this->contrato($norte, $ativoDoContrato);
        $this->politica(120, ['contract_id' => $contrato->id]);

        $this->assertSame(120, $this->meta($this->os($ativoDoContrato, $norte)), 'contrato > cliente > geral');
        $this->assertSame(480, $this->meta($this->os(null, $norte)), 'mesmo cliente, outro equipamento (sem o contrato)');
        $this->assertSame(120, $this->meta($this->os($ativoDoContrato)), 'sem cliente na OS: vale o do contrato');
    }

    public function test_tipo_especifico_do_mesmo_nivel_ainda_vence_mas_cliente_vence_o_tipo(): void
    {
        $norte = $this->cliente('Construtora Norte');
        $this->politica(1440);
        $this->politica(300, ['maintenance_type' => 'Corretiva']);
        $this->politica(600, ['client_id' => $norte->id]);

        $this->assertSame(300, $this->meta($this->os()), 'tipo vence a geral');
        $this->assertSame(600, $this->meta($this->os(null, $norte)), 'cliente vence o tipo');
    }

    public function test_politica_inativa_ou_de_outra_empresa_e_ignorada(): void
    {
        $norte = $this->cliente('Construtora Norte');
        $this->politica(1440);
        $this->politica(240, ['client_id' => $norte->id, 'is_active' => false]);

        $this->assertSame(1440, $this->meta($this->os(null, $norte)));

        $outra = Tenant::create(['name' => 'Outra '.uniqid(), 'slug' => 'f6o-'.uniqid(), 'plan_id' => $this->tenant->plan_id, 'status' => 'active']);
        SlaPolicy::withoutGlobalScopes()->create(['tenant_id' => $outra->id, 'priority_code' => 'alta', 'resolution_minutes' => 5, 'is_active' => true]);
        $this->assertSame(1440, $this->meta($this->os(null, $norte)));
    }

    public function test_cadastro_aceita_cliente_e_contrato_e_barra_duplicidade_no_mesmo_escopo(): void
    {
        $norte = $this->cliente('Construtora Norte');

        Livewire::test(CreateSlaPolicy::class)
            ->fillForm(['priority_code' => 'alta', 'client_id' => $norte->id, 'resolution_minutes' => 240, 'is_active' => true])
            ->call('create')->assertHasNoFormErrors();

        $this->assertSame($norte->id, SlaPolicy::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->value('client_id'));

        Livewire::test(CreateSlaPolicy::class)
            ->fillForm(['priority_code' => 'alta', 'client_id' => $norte->id, 'resolution_minutes' => 100, 'is_active' => true])
            ->call('create')->assertHasFormErrors(['priority_code']);

        Livewire::test(CreateSlaPolicy::class)
            ->fillForm(['priority_code' => 'alta', 'resolution_minutes' => 100, 'is_active' => true])
            ->call('create')->assertHasNoFormErrors();
    }

    public function test_pacote_offline_traz_os_relogios_abertos_com_prazo_e_inicio_do_risco(): void
    {
        $this->politica(100);
        $tec = User::create(['name' => 'Tec', 'email' => 'tec-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $tec->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $role = Role::firstOrCreate(['name' => 'tecnico-f6', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        foreach (['ler_ordem_servico', 'ler_ativo'] as $permissao) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }
        $tec->assignRole($role);

        $aberta = $this->os(null, null, ['technician_id' => $tec->id]);
        $comPausa = $this->os(null, null, ['technician_id' => $tec->id]);
        $comPausa->update(['internal_status' => 'aguardando_peca']);
        $concluida = $this->os(null, null, ['technician_id' => $tec->id, 'status' => 'Concluída']);
        $semPolitica = $this->os(null, null, ['technician_id' => $tec->id, 'priority' => 'baixa']);

        $json = $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($tec)->getJson('/colaborador/api/pacote')->assertOk()->json();

        $sla = $json['os'][$aberta->id]['sla'];
        $this->assertCount(1, $sla);
        $this->assertSame('Resolução', $sla[0]['rel']);
        $this->assertFalse($sla[0]['pausado']);
        $this->assertSame('2026-11-09T13:40:00', Carbon::parse($sla[0]['due'])->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s'));
        $this->assertSame('2026-11-09T13:10:00', Carbon::parse($sla[0]['risco'])->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s'), '70 min de 100');

        $this->assertTrue($json['os'][$comPausa->id]['sla'][0]['pausado']);
        $this->assertNull($json['os'][$comPausa->id]['sla'][0]['due']);
        $this->assertSame([], $json['os'][$concluida->id]['sla'], 'OS fechada não leva relógio');
        $this->assertSame([], $json['os'][$semPolitica->id]['sla']);
    }

    public function test_inicio_do_risco_respeita_o_calendario_comercial(): void
    {
        SlaService::salvarSettings((string) $this->tenant->id, SlaService::PAUSA_PADRAO, 50, 150, ['modo' => 'comercial', 'timezone' => 'America/Sao_Paulo', 'horarios' => null]);
        $this->politica(480);
        Carbon::setTestNow(Carbon::parse('2026-11-06 16:00', 'America/Sao_Paulo')); // sexta
        $os = $this->os();

        $t = SlaTimer::withoutGlobalScopes()->where('subject_id', $os->id)->firstOrFail();
        // 50% de 480 = 240 min úteis: sexta 16h-18h (120) + segunda 8h-10h (120)
        $this->assertSame('Mon 2026-11-09 10:00', $t->inicioDoRisco(50)->copy()->setTimezone('America/Sao_Paulo')->format('D Y-m-d H:i'));
    }
}
