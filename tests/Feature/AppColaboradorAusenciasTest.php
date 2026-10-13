<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Faltas e ausências no app do colaborador: foto do atestado, CID, explicação e histórico. */
class AppColaboradorAusenciasTest extends TestCase
{
    use DatabaseTransactions;

    private const CELULAR = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    private function usuario(?Tenant $tenant = null, string $nome = 'Joana'): User
    {
        if (! $tenant) {
            $plan = Plan::create(['name' => 'Plano Aus '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_absences', 'modulo_app_colaborador']]);
            $tenant = Tenant::create(['name' => 'Locadora Aus', 'slug' => 'aus-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        }
        $user = User::create(['name' => $nome, 'email' => 'aus-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $user->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        Employee::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'name' => $nome, 'cpf' => (string) random_int(10000000000, 99999999999), 'status' => 'active']);

        return $user->refresh();
    }

    private function celular(): self
    {
        return $this->withHeaders(['User-Agent' => self::CELULAR])->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_registra_ausencia_com_foto_cid_e_explicacao(): void
    {
        Storage::fake('local');
        $u = $this->usuario();

        $this->celular()->actingAs($u)->post('/colaborador/ausencias', [
            'type' => 'atestado_medico', 'start_date' => now()->subDays(2)->toDateString(), 'end_date' => now()->toDateString(),
            'reason' => 'Gripe forte', 'cid_code' => 'j11', 'details' => 'Febre e dor no corpo.',
            'anexos' => [UploadedFile::fake()->image('atestado.jpg')],
        ])->assertRedirect(route('colab.ausencias'));

        $a = Absence::firstOrFail();
        $this->assertSame('J11', $a->cid_code);
        $this->assertSame('pendente', $a->status);
        $this->assertSame(3, $a->days);
        $this->assertCount(1, $a->attachments);
        Storage::disk('local')->assertExists($a->attachments[0]);

        $this->celular()->actingAs($u)->get('/colaborador/ausencias')->assertOk()->assertSee('Gripe forte')->assertSee('CID J11')->assertSee('Atestado médico');
    }

    public function test_valida_cid_e_datas(): void
    {
        $u = $this->usuario();
        $base = ['type' => 'atestado_medico', 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(), 'reason' => 'x'];

        $this->celular()->actingAs($u)->post('/colaborador/ausencias', $base + ['cid_code' => 'zzz'])->assertSessionHasErrors('cid_code');
        $this->celular()->actingAs($u)->post('/colaborador/ausencias', array_merge($base, ['end_date' => now()->subDay()->toDateString()]))->assertSessionHasErrors('end_date');
        $this->celular()->actingAs($u)->post('/colaborador/ausencias', array_merge($base, ['start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(2)->toDateString()]))->assertSessionHasErrors('start_date');
        $this->celular()->actingAs($u)->post('/colaborador/ausencias', $base + ['cid_code' => 'M54.5'])->assertSessionHasNoErrors();
        $this->assertSame('M545', Absence::firstOrFail()->cid_code);
    }

    public function test_historico_so_mostra_as_proprias_e_anexo_e_protegido(): void
    {
        Storage::fake('local');
        $u = $this->usuario();
        $outro = $this->usuario($u->tenant, 'Marcos');
        $emp = Employee::where('user_id', $outro->id)->first();
        Storage::disk('local')->put('absences/x.jpg', 'img');
        $alheia = Absence::create(['tenant_id' => $u->tenant_id, 'employee_id' => $emp->id, 'type' => 'atestado_medico', 'start_date' => now(), 'end_date' => now(), 'reason' => 'Do Marcos', 'attachments' => ['absences/x.jpg']]);

        $this->celular()->actingAs($u)->get('/colaborador/ausencias')->assertOk()->assertDontSee('Do Marcos');

        // Quem é admin do tenant (RH) abre; um colaborador sem permissão de editar não.
        $this->celular()->actingAs($u)->get("/colaborador/ausencias/{$alheia->id}/anexo/0")->assertOk();
        $this->celular()->actingAs($outro)->get("/colaborador/ausencias/{$alheia->id}/anexo/0")->assertOk();
        $this->celular()->actingAs($u)->get("/colaborador/ausencias/{$alheia->id}/anexo/9")->assertNotFound();
    }

    public function test_aparece_no_inicio_sob_departamento_pessoal(): void
    {
        $this->celular()->actingAs($this->usuario())->get('/colaborador')->assertOk()->assertSee('Faltas e ausências');
    }

    private function acao(string $tipo, array $dados, ?string $uuid = null): array
    {
        return ['uuid' => $uuid ?? (string) Str::uuid(), 'tipo' => $tipo, 'dados' => $dados, 'criado_em' => now()->toDateTimeString()];
    }

    public function test_fila_offline_registra_ausencia_uma_vez_e_anexa_a_foto(): void
    {
        Storage::fake('local');
        $u = $this->usuario();
        $id = (string) Str::uuid();
        $dados = ['type' => 'atestado_medico', 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->toDateString(), 'reason' => 'Dor nas costas', 'cid_code' => 'M54.5', 'details' => 'Ficou sem andar.'];

        $envia = fn (array $acao, $arquivo = null) => $this->celular()->actingAs($u)->post('/colaborador/api/fila', $arquivo ? ['acao' => json_encode($acao), 'arquivo' => $arquivo] : ['acoes' => [$acao]], ['Accept' => 'application/json']);

        $envia($this->acao('ausencia_registrar', $dados, $id))->assertOk()->assertJsonPath('resultados.0.status', 'ok');
        $envia($this->acao('ausencia_registrar', $dados, $id))->assertOk()->assertJsonPath('resultados.0.status', 'ok'); // reenvio
        $envia($this->acao('ausencia_anexo', ['ausencia_id' => $id]), UploadedFile::fake()->image('atestado.jpg'))->assertJsonPath('resultados.0.status', 'ok');

        $this->assertSame(1, Absence::count());
        $a = Absence::findOrFail($id);
        $this->assertSame(['M545', 'pendente', 2], [$a->cid_code, $a->status, $a->days]);
        $this->assertCount(1, $a->attachments);
        Storage::disk('local')->assertExists($a->attachments[0]);
    }

    public function test_fila_offline_recusa_ausencia_invalida_e_anexo_de_ausencia_inexistente(): void
    {
        Storage::fake('local');
        $u = $this->usuario();

        $r = $this->celular()->actingAs($u)->postJson('/colaborador/api/fila', ['acoes' => [
            $this->acao('ausencia_registrar', ['type' => 'atestado_medico', 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(), 'reason' => 'x', 'cid_code' => 'zzz']),
        ]])->assertOk();
        $this->assertSame('erro', $r->json('resultados.0.status'));
        $this->assertStringContainsString('CID inválido', $r->json('resultados.0.mensagem'));

        $r = $this->celular()->actingAs($u)->post('/colaborador/api/fila', ['acao' => json_encode($this->acao('ausencia_anexo', ['ausencia_id' => (string) Str::uuid()])), 'arquivo' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('erro', $r->json('resultados.0.status'));
        $this->assertSame(0, Absence::count());
    }

    public function test_anexo_offline_nao_vai_para_ausencia_de_outro_colaborador(): void
    {
        Storage::fake('local');
        $u = $this->usuario();
        $outro = $this->usuario($u->tenant, 'Marcos');
        $alheia = Absence::create(['tenant_id' => $u->tenant_id, 'employee_id' => Employee::where('user_id', $outro->id)->value('id'), 'type' => 'outro', 'start_date' => now(), 'end_date' => now(), 'reason' => 'x']);

        $r = $this->celular()->actingAs($u)->post('/colaborador/api/fila', ['acao' => json_encode($this->acao('ausencia_anexo', ['ausencia_id' => $alheia->id])), 'arquivo' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json']);
        $this->assertSame('erro', $r->json('resultados.0.status'));
        $this->assertNull($alheia->fresh()->attachments);
    }

    public function test_telas_oferecem_o_envio_offline_e_o_js_conhece_os_tipos(): void
    {
        $u = $this->usuario();
        $this->celular()->actingAs($u)->get('/colaborador/ausencias/nova')->assertOk()->assertSee('data-colab-ausencia', false)->assertSee('aus-erro', false);
        $this->celular()->actingAs($u)->get('/colaborador/ausencias')->assertOk()->assertSee('aus-fila', false);
        $this->celular()->actingAs($u)->get('/colaborador')->assertOk()->assertSee('ausencias\/nova', false);
        $js = file_get_contents(public_path('js/colab-offline.js'));
        $this->assertStringContainsString("'ausencia_registrar'", $js);
        $this->assertStringContainsString("'ausencia_anexo'", $js);
    }
}
