<?php

namespace Tests\Feature;

use App\Filament\Pages\AcessoAppMobile;
use App\Models\Absence;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Colaborador\Modulos;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** O administrador da empresa libera, usuário a usuário, os módulos e funções do App do Colaborador. */
class AppColaboradorAcessoPorUsuarioTest extends TestCase
{
    use DatabaseTransactions;

    private const CELULAR = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $plan = Plan::create(['name' => 'Plano Acesso '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_absences', 'tabela_assets', 'tabela_frota_saidas_veiculo', 'tabela_frota_abastecimentos', 'tabela_frota_vinculos_motorista', 'modulo_app_colaborador']]);
        $this->tenant = Tenant::create(['name' => 'Locadora Acesso', 'slug' => 'ac-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function usuario(?array $appAccess, string $nome = 'Joana'): User
    {
        $u = User::create(['name' => $nome, 'email' => 'ac-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true, 'app_access' => $appAccess])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]));
        Employee::create(['tenant_id' => $this->tenant->id, 'user_id' => $u->id, 'name' => $nome, 'cpf' => (string) random_int(10000000000, 99999999999), 'status' => 'active']);

        return $u->refresh();
    }

    private function celular(User $u): self
    {
        return $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($u);
    }

    public function test_usuario_nunca_configurado_continua_vendo_tudo_como_antes(): void
    {
        $u = $this->usuario(null);

        $this->celular($u)->get('/colaborador')->assertOk()->assertSee('Faltas e ausências')->assertSee('Logística');
        $this->celular($u)->get('/colaborador/ausencias')->assertOk();
        $this->celular($u)->get('/colaborador/logistica')->assertOk();
    }

    public function test_so_o_que_o_administrador_marcou_aparece_e_abre(): void
    {
        $u = $this->usuario(['ausencias']);

        $this->celular($u)->get('/colaborador')->assertOk()->assertSee('Faltas e ausências')->assertDontSee('Logística')->assertDontSee('Registro de ponto');
        $this->celular($u)->get('/colaborador/ausencias')->assertOk();
        $this->celular($u)->get('/colaborador/logistica')->assertForbidden();
        $this->celular($u)->get('/colaborador/logistica/abastecimento')->assertForbidden();
    }

    public function test_funcao_dentro_do_modulo_e_liberada_separadamente(): void
    {
        $u = $this->usuario(['logistica', 'logistica.km']);

        $this->celular($u)->get('/colaborador/logistica')->assertOk()->assertSee('Marcar km')->assertDontSee('Abastecimento')->assertDontSee('Entrada e saída');
        $this->celular($u)->get('/colaborador/logistica/km')->assertOk();
        $this->celular($u)->get('/colaborador/logistica/abastecimento')->assertForbidden();
        $this->celular($u)->get('/colaborador/logistica/saida')->assertForbidden();
        $this->celular($u)->get('/colaborador/logistica/vencimentos')->assertForbidden();
    }

    public function test_lista_vazia_tira_o_acesso_a_tudo(): void
    {
        $u = $this->usuario([]);

        $this->celular($u)->get('/colaborador')->assertOk()->assertSee('Nenhuma função disponível');
        $this->celular($u)->get('/colaborador/ausencias')->assertForbidden();
    }

    public function test_fila_offline_recusa_funcao_retirada_depois_de_guardada_no_celular(): void
    {
        $u = $this->usuario(['logistica', 'logistica.km']);
        $acao = ['uuid' => (string) Str::uuid(), 'tipo' => 'ausencia_registrar', 'criado_em' => now()->toDateTimeString(), 'dados' => ['type' => 'outro', 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(), 'reason' => 'x']];

        $r = $this->celular($u)->postJson('/colaborador/api/fila', ['acoes' => [$acao]])->assertOk();

        $this->assertSame('erro', $r->json('resultados.0.status'));
        $this->assertStringContainsString('não tem acesso', $r->json('resultados.0.mensagem'));
        $this->assertSame(0, Absence::count());
    }

    public function test_pacote_offline_nao_leva_dados_de_logistica_sem_acesso(): void
    {
        $sem = $this->usuario(['ausencias']);
        $this->assertNull($this->celular($sem)->getJson('/colaborador/api/pacote')->assertOk()->json('frota'));

        $com = $this->usuario(['logistica', 'logistica.saida']);
        $frota = $this->celular($com)->getJson('/colaborador/api/pacote')->assertOk()->json('frota');
        $this->assertNotNull($frota);
        $this->assertTrue($frota['perm']['saida']);
        $this->assertFalse($frota['perm']['abastecimento']);
    }

    public function test_catalogo_so_traz_o_que_o_contrato_libera(): void
    {
        $chaves = collect(Modulos::catalogo($this->tenant))->flatten(1)->pluck('chave')->all();
        $this->assertContains('ausencias', $chaves);
        $this->assertContains('logistica', $chaves);
        $this->assertNotContains('ponto', $chaves, 'o contrato de teste não tem tabela_time_clocks');
    }

    public function test_administrador_configura_o_acesso_de_um_usuario_na_tela(): void
    {
        $admin = $this->usuario(null, 'Chefe');
        $alvo = $this->usuario(['ausencias'], 'Marcos');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)->test(AcessoAppMobile::class)
            ->assertSuccessful()
            ->callTableAction('configurar', $alvo, data: ['personalizar' => true, 'mod_ausencias' => true, 'mod_logistica' => true, 'rec_logistica' => ['km', 'abastecimento']])
            ->assertHasNoTableActionErrors();

        $this->assertEqualsCanonicalizing(['ausencias', 'logistica', 'logistica.km', 'logistica.abastecimento'], $alvo->refresh()->app_access);

        Livewire::actingAs($admin)->test(AcessoAppMobile::class)
            ->callTableAction('configurar', $alvo, data: ['personalizar' => false]);
        $this->assertNull($alvo->refresh()->app_access);
    }

    public function test_quem_nao_e_administrador_nao_abre_a_tela(): void
    {
        $u = User::create(['name' => 'Func', 'email' => 'f-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $this->actingAs($u);

        $this->assertFalse(AcessoAppMobile::canAccess());
    }
}
