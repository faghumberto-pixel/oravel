<?php

namespace Tests\Feature;

use App\Filament\Resources\FrotaChecklistResource\Pages\ListFrotaChecklists;
use App\Livewire\ChecklistFrotaMobile;
use App\Models\Asset;
use App\Models\FrotaChecklist;
use App\Models\FrotaItemModeloChecklist as Item;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaModeloChecklist;
use App\Models\FrotaRespostaChecklist as Resposta;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\ChecklistFrotaService;
use App\Services\Frota\ModelosChecklistPadrao;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Gestão de Frota, Fase 1: checklist de saída e retorno (situação, bloqueio, liberação, odômetro, pareamento). */
class FrotaChecklistTest extends TestCase
{
    use DatabaseTransactions;

    private ChecklistFrotaService $servico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servico = new ChecklistFrotaService;
    }

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_frota_checklists', 'tabela_frota_modelos_checklist']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function usuario(Tenant $tenant, bool $podeLiberar = false): User
    {
        $u = User::create(['name' => 'Func', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        if ($podeLiberar) {
            $u->givePermissionTo(Permission::firstOrCreate(['name' => 'liberar_checklist_frota', 'guard_name' => 'web']));
        }

        return $u;
    }

    private function veiculo(Tenant $tenant, array $extra = []): Asset
    {
        return Asset::create(array_merge(['tenant_id' => $tenant->id, 'name' => 'Caminhão '.uniqid(), 'tag' => 'V-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'grupo' => Asset::GRUPO_VEICULO, 'placa' => 'ABC1D23', 'odometro_atual' => 1000], $extra));
    }

    /** Respostas: tudo OK, exceto os itens indicados (descrição => resultado). */
    private function respostas(FrotaModeloChecklist $modelo, array $excecoes = []): array
    {
        return $modelo->itens->mapWithKeys(fn (Item $i) => [$i->id => [
            'resultado' => $excecoes[$i->descricao] ?? Resposta::OK,
            'valor' => $i->tipo_resposta === Item::RESPOSTA_NUMERO ? '4,5' : null,
        ]])->all();
    }

    private function registrar(Asset $ativo, FrotaModeloChecklist $modelo, array $extra = [], array $excecoes = [], ?User $user = null): FrotaChecklist
    {
        return $this->servico->registrar($ativo, array_merge([
            'tipo' => FrotaChecklist::TIPO_SAIDA, 'modelo_id' => $modelo->id, 'odometro' => 1200,
            'respostas' => $this->respostas($modelo, $excecoes),
        ], $extra), $user);
    }

    private function modelo(Tenant $tenant, string $tipo = FrotaModeloChecklist::TIPO_RAPIDO): FrotaModeloChecklist
    {
        ModelosChecklistPadrao::garantir($tenant->id);

        return FrotaModeloChecklist::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('tipo', $tipo)->with('itens')->firstOrFail();
    }

    public function test_default_models_are_created_once_per_client(): void
    {
        [$a] = $this->cliente();
        [$b] = $this->cliente();

        ModelosChecklistPadrao::garantir($a->id);
        ModelosChecklistPadrao::garantir($a->id);

        $modelos = FrotaModeloChecklist::withoutGlobalScopes()->where('tenant_id', $a->id)->with('itens')->get();
        $this->assertCount(2, $modelos);
        $this->assertSame(9, $modelos->firstWhere('tipo', 'rapido')->itens->count());
        $this->assertSame(15, $modelos->firstWhere('tipo', 'completo')->itens->count());
        $this->assertSame(0, FrotaModeloChecklist::withoutGlobalScopes()->where('tenant_id', $b->id)->count());
    }

    public function test_situation_rules(): void
    {
        $s = fn (array $r) => ChecklistFrotaService::situacaoDas($r);

        $this->assertSame('ok', $s([['gravidade' => 'critica', 'resultado' => 'ok'], ['gravidade' => 'atencao', 'resultado' => 'nao_se_aplica']]));
        $this->assertSame('atencao', $s([['gravidade' => 'atencao', 'resultado' => 'problema'], ['gravidade' => 'critica', 'resultado' => 'ok']]));
        $this->assertSame('bloqueado', $s([['gravidade' => 'atencao', 'resultado' => 'problema'], ['gravidade' => 'critica', 'resultado' => 'problema']]));
        $this->assertSame('ok', $s([['gravidade' => 'informativa', 'resultado' => 'problema']]));
    }

    public function test_a_clean_checklist_is_saved_with_answers_snapshot_and_updates_the_odometer(): void
    {
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $modelo = $this->modelo($tenant);

        $c = $this->registrar($ativo, $modelo, [], [], $admin);

        $this->assertSame(FrotaChecklist::OK, $c->situacao);
        $this->assertSame(9, $c->respostas()->count());
        $this->assertSame('Freios (pedal e freio de mão)', $c->respostas()->where('descricao_registrada', 'like', 'Freios%')->first()->descricao_registrada);
        $this->assertSame(1200.0, (float) $ativo->fresh()->odometro_atual);
        $this->assertSame('checklist', FrotaLeituraOdometro::withoutGlobalScopes()->where('ativo_id', $ativo->id)->sole()->origem);
        $this->assertTrue($ativo->fresh()->podeSair());

        // O texto do item fica como foi respondido, mesmo se o modelo mudar depois.
        $modelo->itens->first()->update(['descricao' => 'Texto alterado depois']);
        $this->assertNotContains('Texto alterado depois', $c->respostas()->pluck('descricao_registrada')->all());
    }

    public function test_a_critical_problem_blocks_the_vehicle_until_someone_with_permission_releases_it_with_a_reason(): void
    {
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $modelo = $this->modelo($tenant);

        $c = $this->registrar($ativo, $modelo, [], ['Freios (pedal e freio de mão)' => Resposta::PROBLEMA], $admin);

        $this->assertSame(FrotaChecklist::BLOQUEADO, $c->situacao);
        $this->assertFalse($ativo->fresh()->podeSair());

        // Sem permissão: não libera.
        try {
            $this->servico->liberar($c, $this->usuario($tenant), 'urgente');
            $this->fail('Usuário sem permissão não deveria liberar.');
        } catch (AuthorizationException) {
            $this->assertFalse($ativo->fresh()->podeSair());
        }

        // Com permissão, mas sem motivo: não libera.
        $liberador = $this->usuario($tenant, podeLiberar: true);
        $this->expectException(ValidationException::class);
        try {
            $this->servico->liberar($c, $liberador, '   ');
        } finally {
            $this->assertFalse($ativo->fresh()->podeSair());
        }
    }

    public function test_release_with_permission_and_reason_frees_the_vehicle_and_is_recorded(): void
    {
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $c = $this->registrar($ativo, $this->modelo($tenant), [], ['Documentos do veículo e CNH' => Resposta::PROBLEMA], $admin);
        $this->assertFalse($ativo->fresh()->podeSair());

        $liberador = $this->usuario($tenant, podeLiberar: true);
        $this->servico->liberar($c, $liberador, 'Documento digital conferido pelo gerente');

        $c->refresh();
        $this->assertSame(FrotaChecklist::LIBERADO, $c->situacao);
        $this->assertSame($liberador->id, $c->liberado_por);
        $this->assertSame('Documento digital conferido pelo gerente', $c->motivo_liberacao);
        $this->assertTrue($ativo->fresh()->podeSair());

        // Administrador do cliente também pode, em outro bloqueio.
        $c2 = $this->registrar($ativo->fresh(), $this->modelo($tenant), ['odometro' => 1300], ['Freios (pedal e freio de mão)' => Resposta::PROBLEMA], $admin);
        $this->servico->liberar($c2, $admin, 'Reparo feito na hora');
        $this->assertSame(FrotaChecklist::LIBERADO, $c2->fresh()->situacao);

        // Não libera duas vezes.
        $this->expectException(ValidationException::class);
        $this->servico->liberar($c2->fresh(), $admin, 'de novo');
    }

    public function test_lower_odometer_is_refused_without_justification_and_nothing_is_saved(): void
    {
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $modelo = $this->modelo($tenant);

        try {
            $this->registrar($ativo, $modelo, ['odometro' => 900], [], $admin);
            $this->fail('Odômetro menor deveria ser recusado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('odometro', $e->errors());
        }
        $this->assertSame(0, FrotaChecklist::withoutGlobalScopes()->where('ativo_id', $ativo->id)->count());
        $this->assertSame(1000.0, (float) $ativo->fresh()->odometro_atual);

        // Com justificativa, entra (ex.: painel trocado).
        $this->registrar($ativo, $modelo, ['odometro' => 900, 'justificativa_odometro' => 'Painel trocado'], [], $admin);
        $this->assertSame(900.0, (float) $ativo->fresh()->odometro_atual);
        $this->assertSame('Painel trocado', FrotaLeituraOdometro::withoutGlobalScopes()->where('ativo_id', $ativo->id)->sole()->justificativa);
    }

    public function test_odometer_readings_are_append_only(): void
    {
        [$tenant] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $leitura = FrotaLeituraOdometro::registrar($ativo, 1500, 'manual');

        $this->expectException(\LogicException::class);
        try {
            $leitura->update(['odometro' => 1]);
        } finally {
            $this->assertSame(1500, FrotaLeituraOdometro::withoutGlobalScopes()->find($leitura->id)->odometro);
        }
    }

    public function test_return_is_paired_with_the_departure_and_new_problems_are_detected(): void
    {
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $modelo = $this->modelo($tenant);

        $saida = $this->registrar($ativo, $modelo, [], [], $admin);
        $retorno = $this->registrar($ativo->fresh(), $modelo, ['tipo' => FrotaChecklist::TIPO_RETORNO, 'odometro' => 1350], ['Luzes e setas' => Resposta::PROBLEMA], $admin);

        $this->assertSame($saida->id, $retorno->checklist_par_id);
        $this->assertSame(['Luzes e setas'], $retorno->novosProblemasNoRetorno()->pluck('descricao_registrada')->all());

        // A mesma saída não é pareada duas vezes.
        $outroRetorno = $this->registrar($ativo->fresh(), $modelo, ['tipo' => FrotaChecklist::TIPO_RETORNO, 'odometro' => 1360], [], $admin);
        $this->assertNull($outroRetorno->checklist_par_id);
    }

    public function test_full_checklist_is_pending_when_missing_or_older_than_seven_days(): void
    {
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);

        $this->assertTrue($ativo->checklistCompletoVencido());

        $completo = $this->registrar($ativo, $this->modelo($tenant, FrotaModeloChecklist::TIPO_COMPLETO), [], [], $admin);
        $this->assertFalse($ativo->fresh()->checklistCompletoVencido());

        $completo->forceFill(['concluido_em' => now()->subDays(8)])->save();
        $this->assertTrue($ativo->fresh()->checklistCompletoVencido());

        // Checklist rápido não conta como completo.
        $ativo2 = $this->veiculo($tenant, ['placa' => 'XYZ9A87']);
        $this->registrar($ativo2, $this->modelo($tenant), [], [], $admin);
        $this->assertTrue($ativo2->fresh()->checklistCompletoVencido());
    }

    public function test_only_vehicles_have_checklists_and_clients_never_see_each_others_data(): void
    {
        [$tenantA, $adminA] = $this->cliente();
        [$tenantB, $adminB] = $this->cliente();
        $maquina = Asset::create(['tenant_id' => $tenantA->id, 'name' => 'Empilhadeira', 'tag' => 'M-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL]);
        $veiculoA = $this->veiculo($tenantA);
        $modeloA = $this->modelo($tenantA);
        $this->registrar($veiculoA, $modeloA, [], [], $adminA);

        try {
            $this->registrar($maquina, $modeloA, [], [], $adminA);
            $this->fail('Máquina não pode ter checklist da frota.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        ModelosChecklistPadrao::garantir($tenantB->id);
        $this->actingAs($adminB);
        $this->assertSame(0, FrotaChecklist::count());
        $this->assertSame(2, FrotaModeloChecklist::count());
        $this->assertNotContains($modeloA->id, FrotaModeloChecklist::pluck('id')->all());
    }

    public function test_mobile_page_sends_a_checklist_end_to_end_with_photos_and_signature(): void
    {
        Storage::fake('public');
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $this->actingAs($admin);

        $pagina = Livewire::test(ChecklistFrotaMobile::class, ['assetId' => $ativo->id])->assertSuccessful()->assertSee('Checklist rápido');
        $itens = $pagina->instance()->itens;
        $this->assertCount(9, $itens);

        $pagina->set('odometro', '1250')->set('combustivel', '1/2');
        foreach ($itens as $item) {
            $pagina->call('marcar', $item->id, $item->descricao === 'Luzes e setas' ? 'problema' : 'ok');
        }
        $pagina->set('fotoFrente', UploadedFile::fake()->image('f.jpg'))->set('fotoTraseira', UploadedFile::fake()->image('t.jpg'))
            ->set('fotoEsquerda', UploadedFile::fake()->image('e.jpg'))->set('fotoDireita', UploadedFile::fake()->image('d.jpg'))
            ->call('salvarAssinatura', 'data:image/png;base64,iVBORw0KGgo=')
            ->call('enviar')
            ->assertHasNoErrors();

        $c = FrotaChecklist::firstOrFail();
        $this->assertSame(FrotaChecklist::ATENCAO, $c->situacao);
        $this->assertSame(4, $c->getMedia('laterais')->count());
        $this->assertSame(1250.0, (float) $ativo->fresh()->odometro_atual);
        $this->assertSame($admin->id, $c->preenchido_por);
    }

    public function test_mobile_page_demands_the_photos_and_the_odometer_rule_and_hides_machines(): void
    {
        Storage::fake('public');
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $this->actingAs($admin);

        $pagina = Livewire::test(ChecklistFrotaMobile::class, ['assetId' => $ativo->id]);
        foreach ($pagina->instance()->itens as $item) {
            $pagina->call('marcar', $item->id, 'ok');
        }
        $pagina->set('odometro', '500')->call('enviar')->assertHasErrors(['fotoFrente', 'fotoTraseira', 'fotoEsquerda', 'fotoDireita', 'assinatura']);
        $this->assertSame(0, FrotaChecklist::count());

        $maquina = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador', 'tag' => 'G-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL]);
        $this->get(route('frota.checklist.mobile', ['assetId' => $maquina->id]))->assertNotFound();
    }

    public function test_history_screen_lists_checklists_and_releases_a_blocked_vehicle(): void
    {
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $bloqueado = $this->registrar($ativo, $this->modelo($tenant), [], ['Freios (pedal e freio de mão)' => Resposta::PROBLEMA], $admin);
        $this->actingAs($admin);

        Livewire::test(ListFrotaChecklists::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$bloqueado])
            ->assertTableActionVisible('liberar', $bloqueado)
            ->callTableAction('liberar', $bloqueado, ['motivo' => 'Freio revisado na oficina']);

        $this->assertSame(FrotaChecklist::LIBERADO, $bloqueado->fresh()->situacao);
    }
}
