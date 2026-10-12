<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Colaborador\SituacaoOs;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** App do colaborador: situação do ativo (liberado / em manutenção / aguardando peça), OS em aberto e dossiê da OS. */
class AppColaboradorOsTest extends TestCase
{
    use DatabaseTransactions;

    private const CELULAR = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    private function gestor(): User
    {
        $plan = Plan::create(['name' => 'Plano OS '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets', 'tabela_maintenance_orders', 'modulo_app_colaborador']]);
        $tenant = Tenant::create(['name' => 'Locadora OS', 'slug' => 'os-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $user = User::create(['name' => 'Gestor', 'email' => 'os-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $user->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return $user->refresh();
    }

    private function celular(): self
    {
        return $this->withHeaders(['User-Agent' => self::CELULAR]);
    }

    private function os(User $u, Asset $a, array $extra = []): MaintenanceOrder
    {
        $tec = User::create(['name' => 'Carlos Técnico', 'email' => 'tec-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $u->tenant_id]);

        $os = MaintenanceOrder::withoutEvents(fn () => MaintenanceOrder::create(array_merge([
            'tenant_id' => $u->tenant_id, 'asset_id' => $a->id, 'os_number' => 'OS-TESTE-'.random_int(1000, 9999),
            'status' => 'Aberto', 'internal_status' => 'em_manutencao', 'technician_id' => $tec->id,
            'description' => 'Vazamento no cilindro', 'priority' => 'alta',
        ], $extra)));
        $os->forceFill(['created_at' => now()->subDays(5)])->save();

        return $os->refresh();
    }

    public function test_ativo_sem_os_aberta_aparece_liberado(): void
    {
        $u = $this->gestor();
        $a = Asset::create(['tenant_id' => $u->tenant_id, 'name' => 'Guindaste Livre', 'patrimonio' => 'PAT-L1', 'status' => 'disponivel']);

        $this->assertSame('Liberado', SituacaoOs::doAtivo($a)['rotulo']);
        $this->celular()->actingAs($u)->get('/colaborador/dossie/'.$a->id)->assertOk()->assertSee('Liberado');
    }

    public function test_ativo_com_os_mostra_situacao_numero_dias_e_tecnico(): void
    {
        $u = $this->gestor();
        $a = Asset::create(['tenant_id' => $u->tenant_id, 'name' => 'Guindaste Parado', 'patrimonio' => 'PAT-M1', 'status' => 'manutencao']);
        $os = $this->os($u, $a);

        $r = $this->celular()->actingAs($u)->get('/colaborador/dossie/'.$a->id)->assertOk();
        $r->assertSee('Em manutenção')->assertSee($os->os_number)->assertSee('5 dias')->assertSee('Carlos Técnico')
            ->assertSee(route('colab.os', ['id' => $os->id]), false);
    }

    public function test_aguardando_peca_vence_em_manutencao_e_os_concluida_nao_conta(): void
    {
        $u = $this->gestor();
        $a = Asset::create(['tenant_id' => $u->tenant_id, 'name' => 'Empilhadeira', 'patrimonio' => 'PAT-P1', 'status' => 'manutencao']);
        $this->os($u, $a, ['internal_status' => 'em_manutencao']);
        $this->os($u, $a, ['internal_status' => 'aguardando_peca']);
        $this->os($u, $a, ['status' => 'Concluída', 'internal_status' => 'concluido']);

        $situacao = SituacaoOs::doAtivo($a);

        $this->assertSame('Aguardando peça', $situacao['rotulo']);
        $this->assertCount(2, $situacao['abertas']);
    }

    public function test_dossie_da_os_abre_por_id_e_por_numero_e_respeita_o_tenant(): void
    {
        $u = $this->gestor();
        $a = Asset::create(['tenant_id' => $u->tenant_id, 'name' => 'Plataforma', 'patrimonio' => 'PAT-D1', 'status' => 'manutencao']);
        $os = $this->os($u, $a);

        $this->celular()->actingAs($u)->get('/colaborador/os/'.$os->id)->assertOk()
            ->assertSee('OS '.$os->os_number)->assertSee('Plataforma')->assertSee('Carlos Técnico')->assertSee('5 dias')->assertSee('Vazamento no cilindro');

        $this->celular()->actingAs($u)->get('/colaborador/os?numero='.$os->os_number)->assertRedirect(route('colab.os', ['id' => $os->id]));
        $this->celular()->actingAs($u)->get('/colaborador/os?numero=NAO-EXISTE-999')->assertOk()->assertSee('Nenhuma ordem de serviço encontrada');

        $outro = $this->gestor();
        $this->celular()->actingAs($outro)->get('/colaborador/os/'.$os->id)->assertNotFound();
    }

    public function test_dias_em_aberto_de_os_concluida_contam_ate_a_conclusao(): void
    {
        $u = $this->gestor();
        $a = Asset::create(['tenant_id' => $u->tenant_id, 'name' => 'Teste', 'status' => 'disponivel']);
        $os = $this->os($u, $a, ['status' => 'Concluída', 'internal_status' => 'concluido', 'finished_at' => now()->subDays(2)]);

        $this->assertSame(3, SituacaoOs::diasEmAberto($os));
    }
}
