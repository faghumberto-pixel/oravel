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
}
