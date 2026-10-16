<?php

namespace Tests\Feature;

use App\Filament\Pages\CrmMetasPainel;
use App\Filament\Resources\CrmLeadResource\Pages\ListCrmLeads;
use App\Filament\Resources\CrmLeadResource\Widgets\CrmMinhasMetasStats;
use App\Filament\Resources\CrmMetaResource;
use App\Models\CrmEtapa;
use App\Models\CrmLead;
use App\Models\CrmMeta;
use App\Models\CrmVisita;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CrmFunilPadrao;
use App\Services\CrmMetasService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Fase 6C: metas de vendas por vendedor/mês e fechamento datado dos leads. */
class CrmMetasTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(?array $features = null): Tenant
    {
        $plan = Plan::create(['name' => 'Plano Metas '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => $features ?? ['tabela_crm_leads' => true, 'tabela_crm_metas' => true, 'tabela_crm_visitas' => true]]);

        return Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function admin(Tenant $t): User
    {
        $u = User::create(['name' => 'Admin '.uniqid(), 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return $u;
    }

    private function vendedor(Tenant $t): User
    {
        $u = User::create(['name' => 'Vendedor '.uniqid(), 'email' => 'v-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'comercial', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        foreach (['crm_lead', 'crm_meta'] as $slug) {
            foreach (['ler', 'criar', 'editar'] as $acao) {
                Permission::firstOrCreate(['name' => "{$acao}_{$slug}", 'guard_name' => 'web']);
                $u->givePermissionTo("{$acao}_{$slug}");
            }
        }

        return $u;
    }

    private function etapa(Tenant $t, string $slug): CrmEtapa
    {
        CrmFunilPadrao::garantir($t->id);

        return CrmEtapa::withoutGlobalScopes()->where('tenant_id', $t->id)->where('slug', $slug)->firstOrFail();
    }

    /** Lead ganho com fechamento numa data específica. */
    private function ganho(Tenant $t, User $v, float $valor, Carbon $quando): CrmLead
    {
        $lead = CrmLead::create(['tenant_id' => $t->id, 'name' => 'L'.uniqid(), 'estimated_value' => $valor, 'assigned_user_id' => $v->id, 'stage' => 'convertido']);
        DB::table('crm_leads')->where('id', $lead->id)->update(['fechado_em' => $quando]);

        return $lead->fresh();
    }

    public function test_closing_stamps_date_value_and_seller_and_reopening_clears_it(): void
    {
        $t = $this->tenant();
        $v = $this->vendedor($t);
        $outro = $this->vendedor($t);
        $lead = CrmLead::create(['tenant_id' => $t->id, 'name' => 'L', 'estimated_value' => 1000, 'assigned_user_id' => $v->id]);
        $this->assertNull($lead->fresh()->fechado_em);

        $lead->update(['etapa_id' => $this->etapa($t, 'convertido')->id]);
        $lead->refresh();
        $this->assertNotNull($lead->fechado_em);
        $this->assertEquals(1000.0, $lead->valor_fechado);
        $this->assertSame($v->id, $lead->vendedor_fechamento_id);

        // Mudanças posteriores não mexem no retrato do fechamento.
        $lead->update(['estimated_value' => 5000, 'assigned_user_id' => $outro->id]);
        $lead->refresh();
        $this->assertEquals(1000.0, $lead->valor_fechado);
        $this->assertSame($v->id, $lead->vendedor_fechamento_id);

        $lead->update(['etapa_id' => $this->etapa($t, 'perdido')->id]);
        $lead->refresh();
        $this->assertNotNull($lead->fechado_em);
        $this->assertNull($lead->valor_fechado, 'perdido não tem valor fechado');

        $lead->update(['etapa_id' => $this->etapa($t, 'qualificado')->id]);
        $lead->refresh();
        $this->assertNull($lead->fechado_em);
        $this->assertNull($lead->vendedor_fechamento_id);
    }

    public function test_realized_sums_only_won_leads_closed_in_the_month_for_the_seller(): void
    {
        $t = $this->tenant();
        $a = $this->vendedor($t);
        $b = $this->vendedor($t);
        $mes = Carbon::create(2026, 10, 1);
        $this->ganho($t, $a, 1000, Carbon::create(2026, 10, 1, 0, 0, 0));      // primeiro instante do mês: entra
        $this->ganho($t, $a, 500, Carbon::create(2026, 10, 31, 23, 59, 59));   // último segundo: entra
        $this->ganho($t, $a, 999, Carbon::create(2026, 9, 30, 23, 59, 59));    // mês anterior: não
        $this->ganho($t, $a, 888, Carbon::create(2026, 11, 1, 0, 0, 0));       // mês seguinte: não
        $this->ganho($t, $b, 700, Carbon::create(2026, 10, 15));
        CrmLead::create(['tenant_id' => $t->id, 'name' => 'Aberto', 'estimated_value' => 9999, 'assigned_user_id' => $a->id]);
        $perdido = CrmLead::create(['tenant_id' => $t->id, 'name' => 'Perdido', 'estimated_value' => 5000, 'assigned_user_id' => $a->id, 'stage' => 'perdido']);
        DB::table('crm_leads')->where('id', $perdido->id)->update(['fechado_em' => Carbon::create(2026, 10, 10)]);

        $svc = app(CrmMetasService::class);
        $ia = $svc->indicadores($t->id, $a->id, $mes, Carbon::create(2026, 10, 20));
        $this->assertEquals(1500.0, $ia['realizado']['valor']);
        $this->assertSame(2, $ia['realizado']['ganhos']);

        $this->assertEquals(2200.0, $svc->indicadores($t->id, null, $mes)['realizado']['valor'], 'empresa soma todos');
        $this->assertSame(0, $svc->indicadores($this->tenant()->id, null, $mes)['realizado']['ganhos'], 'outro tenant nunca entra');
    }

    public function test_visits_count_only_realized_ones_of_the_month_and_seller(): void
    {
        $t = $this->tenant();
        $a = $this->vendedor($t);
        $b = $this->vendedor($t);
        $mk = fn (User $u, string $status, Carbon $quando) => CrmVisita::create(['tenant_id' => $t->id, 'user_id' => $u->id, 'status' => $status, 'agendada_para' => $quando, 'checkout_em' => $status === 'realizada' ? $quando : null]);
        $mk($a, 'realizada', Carbon::create(2026, 10, 5, 10));
        $mk($a, 'realizada', Carbon::create(2026, 10, 6, 10));
        $mk($a, 'cancelada', Carbon::create(2026, 10, 7, 10));
        $mk($a, 'agendada', Carbon::create(2026, 10, 8, 10));
        $mk($a, 'realizada', Carbon::create(2026, 9, 30, 10));
        $mk($b, 'realizada', Carbon::create(2026, 10, 9, 10));

        $svc = app(CrmMetasService::class);
        $this->assertSame(2, $svc->indicadores($t->id, $a->id, Carbon::create(2026, 10, 1))['realizado']['visitas']);
        $this->assertSame(3, $svc->indicadores($t->id, null, Carbon::create(2026, 10, 1))['realizado']['visitas']);
    }

    public function test_attainment_shortfall_pace_and_business_days(): void
    {
        $t = $this->tenant();
        $v = $this->vendedor($t);
        CrmMeta::create(['tenant_id' => $t->id, 'user_id' => $v->id, 'mes' => '2026-10-15', 'meta_valor' => 10000, 'meta_ganhos' => 4, 'meta_visitas' => 20]);
        $this->ganho($t, $v, 2500, Carbon::create(2026, 10, 3));
        $svc = app(CrmMetasService::class);

        // Quarta 21/10/2026: dias úteis de 21 a 31/10 = 8 (21,22,23,26,27,28,29,30).
        $hoje = Carbon::create(2026, 10, 21, 9);
        $this->assertSame(8, $svc->diasUteisRestantes(Carbon::create(2026, 10, 1), Carbon::create(2026, 10, 31)->endOfDay(), $hoje));
        $this->assertSame(0, $svc->diasUteisRestantes(Carbon::create(2026, 9, 1), Carbon::create(2026, 9, 30)->endOfDay(), $hoje), 'mês passado');
        $this->assertSame(21, $svc->diasUteisRestantes(Carbon::create(2026, 11, 1), Carbon::create(2026, 11, 30)->endOfDay(), $hoje), 'mês futuro: todos os úteis');
        $this->assertSame(1, $svc->diasUteisRestantes(Carbon::create(2026, 10, 1), Carbon::create(2026, 10, 31)->endOfDay(), Carbon::create(2026, 10, 30, 8)), 'sexta, último dia útil');
        $this->assertSame(0, $svc->diasUteisRestantes(Carbon::create(2026, 10, 1), Carbon::create(2026, 10, 31)->endOfDay(), Carbon::create(2026, 10, 31, 8)), 'sábado: nada restante');

        $i = $svc->indicadores($t->id, $v->id, Carbon::create(2026, 10, 1), $hoje);
        $this->assertTrue($i['tem_meta']);
        $this->assertSame(25.0, $i['atingimento']['valor']);
        $this->assertEquals(7500.0, $i['falta']['valor']);
        $this->assertSame(937.5, $i['ritmo']['valor']);
        $this->assertSame(25.0, $i['atingimento']['ganhos']);
        $this->assertSame(3, $i['falta']['ganhos']);
        $this->assertSame(0.0, $i['atingimento']['visitas']);
    }

    public function test_no_goal_or_zero_goal_never_divides_by_zero_and_team_goal_logic(): void
    {
        $t = $this->tenant();
        $a = $this->vendedor($t);
        $b = $this->vendedor($t);
        $svc = app(CrmMetasService::class);
        $mes = Carbon::create(2026, 10, 1);

        $sem = $svc->indicadores($t->id, $a->id, $mes);
        $this->assertFalse($sem['tem_meta']);
        $this->assertNull($sem['atingimento']['valor']);
        $this->assertNull($sem['ritmo']['valor']);

        CrmMeta::create(['tenant_id' => $t->id, 'user_id' => $a->id, 'mes' => $mes, 'meta_valor' => 0]);
        $this->assertNull($svc->indicadores($t->id, $a->id, $mes)['atingimento']['valor'], 'meta zero = sem meta');

        // Empresa: soma das individuais quando não há linha de equipe; a linha de equipe, quando existe, manda.
        CrmMeta::create(['tenant_id' => $t->id, 'user_id' => $a->id, 'mes' => $mes->copy()->addMonth(), 'meta_valor' => 1000, 'meta_ganhos' => 2]);
        CrmMeta::create(['tenant_id' => $t->id, 'user_id' => $b->id, 'mes' => $mes->copy()->addMonth(), 'meta_valor' => 3000, 'meta_ganhos' => 1]);
        $soma = $svc->meta($t->id, null, $mes->copy()->addMonth());
        $this->assertSame(4000.0, $soma['valor']);
        $this->assertSame(3, $soma['ganhos']);
        $this->assertNull($soma['visitas']);

        CrmMeta::create(['tenant_id' => $t->id, 'user_id' => null, 'mes' => $mes->copy()->addMonth(), 'meta_valor' => 9000]);
        $this->assertSame(9000.0, $svc->meta($t->id, null, $mes->copy()->addMonth())['valor']);
    }

    public function test_forecast_weights_open_leads_by_stage_probability_and_expected_close_month(): void
    {
        $t = $this->tenant();
        $v = $this->vendedor($t);
        $mk = fn (string $stage, float $valor, ?string $previsao) => CrmLead::create(['tenant_id' => $t->id, 'name' => 'L'.uniqid(), 'estimated_value' => $valor, 'assigned_user_id' => $v->id, 'stage' => $stage, 'previsao_fechamento' => $previsao]);
        $mk('qualificado', 10000, '2026-10-20');   // 50% = 5000
        $mk('novo', 10000, '2026-10-05');          // 10% = 1000
        $mk('qualificado', 10000, '2026-11-02');   // fora do mês
        $mk('qualificado', 10000, null);            // sem previsão
        $mk('convertido', 10000, '2026-10-10');    // já ganho: não é previsto

        $svc = app(CrmMetasService::class);
        $this->assertSame(6000.0, $svc->previsto($t->id, $v->id, Carbon::create(2026, 10, 1), Carbon::create(2026, 10, 31)));
    }

    public function test_resource_enforces_uniqueness_visibility_and_admin_only_editing(): void
    {
        $t = $this->tenant();
        $admin = $this->admin($t);
        $a = $this->vendedor($t);
        $b = $this->vendedor($t);
        CrmMeta::create(['tenant_id' => $t->id, 'user_id' => $a->id, 'mes' => now(), 'meta_valor' => 100]);
        CrmMeta::create(['tenant_id' => $t->id, 'user_id' => $b->id, 'mes' => now(), 'meta_valor' => 200]);
        CrmMeta::create(['tenant_id' => $this->tenant()->id, 'user_id' => null, 'mes' => now(), 'meta_valor' => 999]);

        $this->actingAs($a);
        $this->assertSame(1, CrmMetaResource::getEloquentQuery()->count(), 'vendedor só vê a própria');
        $this->assertFalse(CrmMetaResource::canCreate());
        $this->assertFalse(CrmMetaResource::canEdit(CrmMeta::first()));

        $this->actingAs($admin);
        $this->assertSame(2, CrmMetaResource::getEloquentQuery()->count());
        $this->assertTrue(CrmMetaResource::canCreate());

        Livewire::test(CrmMetaResource\Pages\CreateCrmMeta::class)
            ->fillForm(['user_id' => $a->id, 'mes' => now()->format('Y-m-15'), 'meta_valor' => 50])->call('create')->assertHasFormErrors(['mes']);
        Livewire::test(CrmMetaResource\Pages\CreateCrmMeta::class)
            ->fillForm(['user_id' => null, 'mes' => now()->format('Y-m-15'), 'meta_valor' => 5000])->call('create')->assertHasNoFormErrors();
        $this->assertSame(now()->startOfMonth()->toDateString(), CrmMeta::whereNull('user_id')->first()->mes->toDateString(), 'guarda o dia 1');

        Livewire::test(CrmMetaResource\Pages\CreateCrmMeta::class)
            ->fillForm(['user_id' => null, 'mes' => now()->format('Y-m-01'), 'meta_valor' => 1])->call('create')->assertHasFormErrors(['mes']);
    }

    public function test_replicate_and_copy_previous_month_never_overwrite(): void
    {
        $t = $this->tenant();
        $admin = $this->admin($t);
        $a = $this->vendedor($t);
        $b = $this->vendedor($t);
        $mes = now()->startOfMonth();
        $metaA = CrmMeta::create(['tenant_id' => $t->id, 'user_id' => $a->id, 'mes' => $mes, 'meta_valor' => 100, 'meta_ganhos' => 2]);
        CrmMeta::create(['tenant_id' => $t->id, 'user_id' => $b->id, 'mes' => $mes, 'meta_valor' => 300]);
        $this->actingAs($admin);

        Livewire::test(CrmMetaResource\Pages\ListCrmMetas::class)->callTableAction('replicar', $metaA);
        $prox = CrmMeta::where('user_id', $a->id)->whereDate('mes', $mes->copy()->addMonth()->toDateString())->firstOrFail();
        $this->assertEquals(100, $prox->meta_valor);
        $this->assertSame(2, $prox->meta_ganhos);

        // Já existe a do A no mês seguinte (editada); copiar do mês anterior só cria a do B.
        $prox->update(['meta_valor' => 777]);
        Livewire::test(CrmMetaResource\Pages\ListCrmMetas::class)->callTableAction('replicar', $metaA);
        Livewire::test(CrmMetaResource\Pages\ListCrmMetas::class)->callTableAction('copiarMesAnterior', data: ['destino' => $mes->copy()->addMonth()->toDateString()]);

        $this->assertEquals(777, $prox->fresh()->meta_valor, 'não sobrescreve');
        $this->assertSame(1, CrmMeta::where('user_id', $b->id)->whereDate('mes', $mes->copy()->addMonth()->toDateString())->count());
        $this->assertSame(1, CrmMeta::where('user_id', $a->id)->whereDate('mes', $mes->copy()->addMonth()->toDateString())->count());
    }

    public function test_panel_and_widget_render_for_admin_and_salesperson_with_isolation(): void
    {
        $t = $this->tenant();
        $admin = $this->admin($t);
        $v = $this->vendedor($t);
        $outroVendedor = $this->vendedor($t);
        CrmMeta::create(['tenant_id' => $t->id, 'user_id' => $v->id, 'mes' => now(), 'meta_valor' => 10000, 'meta_ganhos' => 3]);
        CrmMeta::create(['tenant_id' => $t->id, 'user_id' => $outroVendedor->id, 'mes' => now(), 'meta_valor' => 777]);
        $this->ganho($t, $v, 4000, now()->startOfMonth()->addDay());

        $this->actingAs($v);
        Livewire::test(CrmMetasPainel::class)->assertSuccessful()->assertSee('40% da meta')->assertDontSee('Equipe');
        Livewire::test(CrmMinhasMetasStats::class)->assertSuccessful()->assertSee('40% da meta');
        Livewire::test(ListCrmLeads::class)->assertSuccessful();

        $this->actingAs($admin);
        Livewire::test(CrmMetasPainel::class)->assertSuccessful()->assertSee('Empresa / equipe')->assertSee('Equipe')->call('mesAnterior')->call('mesSeguinte')->assertSuccessful();
        Livewire::test(CrmMetaResource\Pages\ListCrmMetas::class)->assertSuccessful();
        Livewire::test(CrmMetaResource\Pages\EditCrmMeta::class, ['record' => CrmMeta::first()->getKey()])->assertSuccessful();
    }

    public function test_plan_gating_and_migration_with_backfill(): void
    {
        $semMetas = $this->tenant(['tabela_crm_leads' => true]);
        $this->actingAs($this->admin($semMetas));
        $this->assertFalse(CrmMetaResource::canViewAny());
        $this->assertFalse(CrmMetasPainel::canAccess());

        $t = $this->tenant();
        $v = $this->vendedor($t);
        $lead = CrmLead::create(['tenant_id' => $t->id, 'name' => 'Antigo', 'estimated_value' => 321, 'assigned_user_id' => $v->id, 'stage' => 'convertido']);
        $migration = require base_path('database/migrations/2026_11_19_100000_create_crm_metas_e_fechamento_dos_leads.php');

        $migration->down();
        $this->assertFalse(Schema::hasTable('crm_metas'));
        $this->assertFalse(Schema::hasColumn('crm_leads', 'fechado_em'));

        $migration->up();
        $lead = DB::table('crm_leads')->where('id', $lead->id)->first();
        $this->assertNotNull($lead->fechado_em, 'backfill de lead já ganho');
        $this->assertEquals(321.0, (float) $lead->valor_fechado);
        $this->assertSame($v->id, $lead->vendedor_fechamento_id);
    }
}
