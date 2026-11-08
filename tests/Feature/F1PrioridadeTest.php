<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceOrderResource\Pages\EditMaintenanceOrder;
use App\Filament\Resources\PriorityLevelResource\Pages\ListPriorityLevels;
use App\Models\AbcMatrix;
use App\Models\Asset;
use App\Models\CriticalityLevel;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\PriorityLevel;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Prioridade\PrioridadeDaOs;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/** F1: prioridade configurável por tenant, padrão automático (criticidade do ativo + tipo) e alteração manual com justificativa e log. */
class F1PrioridadeTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets' => true, 'tabela_maintenance_orders' => true, 'tabela_priority_levels' => true]]);
        $this->tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'f1-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $this->admin = User::create(['name' => 'Ana', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        $this->admin->forceFill(['email_verified_at' => now()])->save();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]));
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function nivel(string $code, bool $urgente, ?string $prioridade, int $ordem = 1): CriticalityLevel
    {
        return CriticalityLevel::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'code' => $code, 'name' => $code, 'color' => '#ef4444', 'is_urgent' => $urgente, 'sort_order' => $ordem, 'default_priority' => $prioridade]);
    }

    private function ativo(?string $nivel = null): Asset
    {
        $a = Asset::create(['tenant_id' => $this->tenant->id, 'name' => 'Gerador', 'tag' => 'AST-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL]);
        if ($nivel) {
            AbcMatrix::create(['tenant_id' => $this->tenant->id, 'asset_id' => $a->id, 'nivel' => $nivel, 'descricao' => 'x']);
        }

        return $a;
    }

    private function os(Asset $a, string $tipo = MaintenanceOrder::TYPE_CORRECTIVE, array $extra = []): MaintenanceOrder
    {
        return MaintenanceOrder::create($extra + ['tenant_id' => $this->tenant->id, 'asset_id' => $a->id, 'description' => 'x', 'maintenance_type' => $tipo, 'status' => 'Pendente']);
    }

    public function test_tenant_sem_niveis_ganha_os_quatro_do_sistema_sem_duplicar(): void
    {
        PrioridadeDaOs::garantirPadrao((string) $this->tenant->id);
        PrioridadeDaOs::garantirPadrao((string) $this->tenant->id);

        $this->assertSame(['critica', 'alta', 'media', 'baixa'], PriorityLevel::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->orderBy('sort_order')->pluck('code')->all());
    }

    public function test_sugestao_segue_criticidade_do_ativo_e_o_tipo_da_os(): void
    {
        $this->nivel('A', true, 'alta', 1);
        $this->nivel('C', false, 'baixa', 3);
        $t = (string) $this->tenant->id;

        $this->assertSame('alta', PrioridadeDaOs::sugerir($t, $this->ativo('A')->id, MaintenanceOrder::TYPE_CORRECTIVE));
        $this->assertSame('baixa', PrioridadeDaOs::sugerir($t, $this->ativo('C')->id, MaintenanceOrder::TYPE_PREVENTIVE));
        $this->assertSame('media', PrioridadeDaOs::sugerir($t, $this->ativo()->id, MaintenanceOrder::TYPE_CORRECTIVE), 'sem Matriz ABC = média');
        $this->assertSame('critica', PrioridadeDaOs::sugerir($t, $this->ativo('A')->id, MaintenanceOrder::TYPE_EMERGENCIA), 'emergência sobe um nível');
        $this->assertSame('alta', PrioridadeDaOs::sugerir($t, $this->ativo()->id, MaintenanceOrder::TYPE_EMERGENCIA));
    }

    public function test_emergencia_nao_passa_do_topo_e_prioridade_inexistente_cai_em_media(): void
    {
        $this->nivel('A', true, 'critica', 1);
        $this->nivel('X', false, 'nao_existe', 2);
        $t = (string) $this->tenant->id;

        $this->assertSame('critica', PrioridadeDaOs::sugerir($t, $this->ativo('A')->id, MaintenanceOrder::TYPE_EMERGENCIA));
        $this->assertSame('media', PrioridadeDaOs::sugerir($t, $this->ativo('X')->id, MaintenanceOrder::TYPE_CORRECTIVE));
    }

    public function test_prioridade_propria_do_tenant_e_usada_e_nao_vaza_para_outro_tenant(): void
    {
        PrioridadeDaOs::garantirPadrao((string) $this->tenant->id);
        PriorityLevel::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'code' => 'planejada', 'name' => 'Planejada', 'color' => '#3b82f6', 'base_level' => 'baixa', 'sort_order' => 5, 'is_system' => false, 'is_active' => true]);
        $this->nivel('P', false, 'planejada', 3);

        $this->assertSame('planejada', PrioridadeDaOs::sugerir((string) $this->tenant->id, $this->ativo('P')->id, MaintenanceOrder::TYPE_PREVENTIVE));

        $outro = Tenant::create(['name' => 'Outro '.uniqid(), 'slug' => 'f1o-'.uniqid(), 'plan_id' => $this->tenant->plan_id, 'status' => 'active']);
        $this->assertArrayNotHasKey('planejada', PrioridadeDaOs::opcoes((string) $outro->id));
    }

    public function test_os_nasce_com_prioridade_automatica_ou_manual_se_informada(): void
    {
        $this->nivel('A', true, 'alta', 1);

        $auto = $this->os($this->ativo('A'));
        $this->assertSame('alta', $auto->fresh()->priority);
        $this->assertSame('auto', $auto->fresh()->priority_source);

        $emergencia = $this->os($this->ativo('A'), MaintenanceOrder::TYPE_EMERGENCIA);
        $this->assertSame('critica', $emergencia->fresh()->priority);

        $manual = $this->os($this->ativo('A'), MaintenanceOrder::TYPE_CORRECTIVE, ['priority' => 'baixa']);
        $this->assertSame('baixa', $manual->fresh()->priority);
        $this->assertSame('manual', $manual->fresh()->priority_source);
    }

    public function test_alterar_exige_justificativa_e_prioridade_valida_e_grava_no_log(): void
    {
        $os = $this->os($this->ativo());

        try {
            PrioridadeDaOs::alterar($os, 'critica', 'ok');
            $this->fail('deveria exigir justificativa');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('justificativa', $e->errors());
        }

        try {
            PrioridadeDaOs::alterar($os, 'inexistente', 'Cliente parou a obra');
            $this->fail('deveria recusar prioridade fora do catálogo');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('priority', $e->errors());
        }

        PrioridadeDaOs::alterar($os, 'critica', 'Cliente parou a obra');

        $os = $os->fresh();
        $this->assertSame('critica', $os->priority);
        $this->assertSame('manual', $os->priority_source);
        $log = Activity::where('log_name', 'prioridade_os')->where('subject_id', $os->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('Cliente parou a obra', $log->properties['justificativa']);
        $this->assertSame('media', $log->properties['de']);
        $this->assertSame('critica', $log->properties['para']);
    }

    public function test_acao_alterar_prioridade_na_tela_da_os_exige_justificativa(): void
    {
        $os = $this->os($this->ativo());

        Livewire::test(EditMaintenanceOrder::class, ['record' => $os->id])
            ->callAction('alterar_prioridade', ['priority' => 'alta', 'justificativa' => ''])
            ->assertHasActionErrors(['justificativa']);

        Livewire::test(EditMaintenanceOrder::class, ['record' => $os->id])
            ->callAction('alterar_prioridade', ['priority' => 'alta', 'justificativa' => 'Equipamento parado no cliente'])
            ->assertHasNoActionErrors();

        $this->assertSame('alta', $os->fresh()->priority);
    }

    public function test_cadastro_de_prioridades_lista_os_niveis_do_sistema_e_protege_o_codigo_do_sistema(): void
    {
        Livewire::test(ListPriorityLevels::class)
            ->assertSee('Crítica')->assertSee('Baixa')
            ->assertTableActionHidden('delete', PriorityLevel::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', 'media')->first());
    }
}
