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
use Spatie\Permission\Models\Permission;
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

    public function test_dossie_da_os_abre_pela_lista_do_equipamento_e_respeita_o_tenant(): void
    {
        $u = $this->gestor();
        $a = Asset::create(['tenant_id' => $u->tenant_id, 'name' => 'Plataforma', 'patrimonio' => 'PAT-D1', 'status' => 'manutencao']);
        $os = $this->os($u, $a);

        $this->celular()->actingAs($u)->get('/colaborador/os/'.$os->id)->assertOk()
            ->assertSee('OS '.$os->os_number)->assertSee('Plataforma')->assertSee('Carlos Técnico')->assertSee('5 dias')->assertSee('Vazamento no cilindro');

        $outro = $this->gestor();
        $this->celular()->actingAs($outro)->get('/colaborador/os/'.$os->id)->assertNotFound();
        $this->celular()->actingAs($outro)->get('/colaborador/os/ativo/'.$a->id)->assertNotFound();
    }

    public function test_os_parte_do_equipamento_por_patrimonio_ou_placa_sem_digitar_numero_de_os(): void
    {
        $u = $this->gestor();
        $a = Asset::create(['tenant_id' => $u->tenant_id, 'name' => 'Caminhão Munck', 'patrimonio' => 'PAT-V1', 'placa' => 'ABC1D23', 'status' => 'manutencao']);
        $aberta = $this->os($u, $a);
        $fechada = $this->os($u, $a, ['status' => 'Concluída', 'internal_status' => 'concluido', 'finished_at' => now()->subDay()]);

        // A tela de busca pede o equipamento, não o número da OS.
        $this->celular()->actingAs($u)->get('/colaborador/os')->assertOk()->assertSee('Ler QR do equipamento')->assertDontSee('Número da ordem de serviço');

        foreach (['PAT-V1', 'abc1d23', 'abc-1d23', 'Munck'] as $termo) {
            $this->celular()->actingAs($u)->get('/colaborador/os?q='.urlencode($termo))->assertRedirect(route('colab.os.ativo', ['id' => $a->id]));
        }

        // QR (?busca=) usa o mesmo caminho.
        $this->celular()->actingAs($u)->get('/colaborador/os?busca=PAT-V1')->assertRedirect(route('colab.os.ativo', ['id' => $a->id]));

        $this->celular()->actingAs($u)->get('/colaborador/os/ativo/'.$a->id)->assertOk()
            ->assertSee('Em aberto (1)')->assertSee('Concluídas recentemente (1)')->assertSee($aberta->os_number)->assertSee($fechada->os_number)
            ->assertSee(route('colab.os', ['id' => $aberta->id]), false)->assertSee('ABC1D23');

        $this->celular()->actingAs($u)->get('/colaborador/os?q=NAO-EXISTE-XYZ')->assertOk()->assertSee('Nenhum equipamento encontrado');
    }

    public function test_equipamento_sem_os_diz_que_nao_tem_e_dossie_leva_a_lista(): void
    {
        $u = $this->gestor();
        $a = Asset::create(['tenant_id' => $u->tenant_id, 'name' => 'Gerador', 'patrimonio' => 'PAT-G1', 'status' => 'disponivel']);

        $this->celular()->actingAs($u)->get('/colaborador/os/ativo/'.$a->id)->assertOk()->assertSee('não tem nenhuma ordem de serviço')->assertSee('Liberado');
        $this->celular()->actingAs($u)->get('/colaborador/dossie/'.$a->id)->assertOk()->assertSee(route('colab.os.ativo', ['id' => $a->id]), false);
    }

    public function test_busca_de_ativo_acha_por_placa_com_ou_sem_hifen(): void
    {
        $u = $this->gestor();
        $this->actingAs($u);
        $a = Asset::create(['tenant_id' => $u->tenant_id, 'name' => 'Carreta', 'placa' => 'ABC-1234', 'status' => 'disponivel']);

        foreach (['ABC-1234', 'abc1234', 'ABC 1234', 'c123'] as $termo) {
            $this->assertTrue(Asset::search($termo)->contains('id', $a->id), $termo);
        }
        $this->assertFalse(Asset::search('ZZZ9999')->contains('id', $a->id));
    }

    public function test_dias_em_aberto_de_os_concluida_contam_ate_a_conclusao(): void
    {
        $u = $this->gestor();
        $a = Asset::create(['tenant_id' => $u->tenant_id, 'name' => 'Teste', 'status' => 'disponivel']);
        $os = $this->os($u, $a, ['status' => 'Concluída', 'internal_status' => 'concluido', 'finished_at' => now()->subDays(2)]);

        $this->assertSame(3, SituacaoOs::diasEmAberto($os));
    }

    private function tecnico(User $gestor): User
    {
        $tec = User::create(['name' => 'Tecnico App', 'email' => 'tec-app-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $gestor->tenant_id]);
        $tec->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $perm = Permission::firstOrCreate(['name' => 'ler_ordem_servico', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'tecnico-app', 'guard_name' => 'web', 'tenant_id' => $gestor->tenant_id]);
        $role->givePermissionTo($perm);
        $tec->assignRole($role);

        return $tec->refresh();
    }

    public function test_tecnico_ve_so_as_proprias_os_com_contador_e_abas(): void
    {
        $gestor = $this->gestor();
        $tec = $this->tecnico($gestor);
        $a = Asset::create(['tenant_id' => $gestor->tenant_id, 'name' => 'Guindaste do Carlos', 'patrimonio' => 'PAT-T1', 'placa' => 'TEC1A23', 'status' => 'manutencao']);
        $outroAtivo = Asset::create(['tenant_id' => $gestor->tenant_id, 'name' => 'Ativo de outro tecnico', 'status' => 'manutencao']);

        $minha = $this->os($gestor, $a, ['technician_id' => $tec->id, 'os_number' => 'OS-MINHA-1']);
        $this->os($gestor, $a, ['technician_id' => $tec->id, 'os_number' => 'OS-MINHA-FECHADA', 'status' => 'Concluída', 'internal_status' => 'concluido', 'finished_at' => now()->subDay()]);
        $this->os($gestor, $outroAtivo, ['os_number' => 'OS-DE-OUTRO']);

        $this->celular()->actingAs($tec)->get('/colaborador')->assertOk()->assertSee('Minhas ordens de serviço');

        $this->celular()->actingAs($tec)->get('/colaborador/minhas-os')->assertOk()
            ->assertSee('Em aberto (1)')->assertSee('OS-MINHA-1')->assertSee('Guindaste do Carlos')->assertSee('TEC1A23')->assertSee('5 dias')
            ->assertSee(route('colab.os', ['id' => $minha->id]), false)
            ->assertDontSee('OS-DE-OUTRO')->assertDontSee('OS-MINHA-FECHADA');

        $this->celular()->actingAs($tec)->get('/colaborador/minhas-os?aba=concluidas')->assertOk()
            ->assertSee('OS-MINHA-FECHADA')->assertDontSee('OS-MINHA-1')->assertDontSee('OS-DE-OUTRO');
    }

    public function test_quem_nao_e_tecnico_nao_ve_minhas_os(): void
    {
        $gestor = $this->gestor();
        $comum = User::create(['name' => 'Comum', 'email' => 'comum-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $gestor->tenant_id]);
        $comum->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();

        $this->celular()->actingAs($comum)->get('/colaborador')->assertOk()->assertDontSee('Minhas ordens de serviço');
    }
}
