<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\ColabFilaProcessada;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Fila de envio do app do colaborador: pacote de dados offline e aplicação idempotente das ações do celular. */
class AppColaboradorFilaOfflineTest extends TestCase
{
    use DatabaseTransactions;

    private const CELULAR = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    private function tenantComTecnico(?Tenant $tenant = null): array
    {
        if (! $tenant) {
            $plan = Plan::create(['name' => 'Plano Fila '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets', 'tabela_maintenance_orders', 'modulo_app_colaborador']]);
            $tenant = Tenant::create(['name' => 'Locadora Fila', 'slug' => 'fila-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        }
        $tec = User::create(['name' => 'Tec '.uniqid(), 'email' => 'fila-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $tec->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $role = Role::firstOrCreate(['name' => 'tecnico-fila', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        foreach (['ler_ordem_servico', 'ler_ativo'] as $permissao) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }
        $tec->assignRole($role);

        return [$tenant, $tec->refresh()];
    }

    private function os(Tenant $t, User $tec, array $extra = []): array
    {
        $ativo = Asset::create(['tenant_id' => $t->id, 'name' => 'Guindaste Fila', 'patrimonio' => 'PAT-F'.random_int(100, 999), 'placa' => 'FIL1A23', 'status' => 'manutencao']);
        $os = MaintenanceOrder::withoutEvents(fn () => MaintenanceOrder::create(array_merge([
            'tenant_id' => $t->id, 'asset_id' => $ativo->id, 'os_number' => 'OS-FILA-'.random_int(1000, 9999),
            'status' => 'Aberto', 'internal_status' => 'em_manutencao', 'technician_id' => $tec->id, 'description' => 'Vazamento',
        ], $extra)));
        $item = MaintenanceOrderChecklist::create(['tenant_id' => $t->id, 'maintenance_order_id' => $os->id, 'item_name' => 'Nível de óleo', 'category' => 'Motor', 'is_template' => false]);

        return [$ativo, $os->refresh(), $item];
    }

    private function enviar(User $user, array $acoes)
    {
        return $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($user)->postJson('/colaborador/api/fila', ['acoes' => $acoes]);
    }

    private function acao(string $tipo, array $dados, ?string $uuid = null): array
    {
        return ['uuid' => $uuid ?? (string) Str::uuid(), 'tipo' => $tipo, 'dados' => $dados, 'criado_em' => now()->toDateTimeString()];
    }

    public function test_pacote_traz_ativos_os_e_o_detalhe_das_minhas_com_checklist(): void
    {
        [$t, $tec] = $this->tenantComTecnico();
        [$ativo, $os, $item] = $this->os($t, $tec);
        [$tOutro, $tecOutro] = $this->tenantComTecnico();
        $this->os($tOutro, $tecOutro);

        $json = $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($tec)->getJson('/colaborador/api/pacote')->assertOk()->json();

        $this->assertSame([$ativo->id], array_column($json['ativos'], 'id'), 'só os ativos do tenant do usuário');
        $this->assertSame('FIL1A23', $json['ativos'][0]['pl']);
        $this->assertSame($os->os_number, $json['os'][$os->id]['num']);
        $this->assertSame($ativo->id, $json['os'][$os->id]['ativo']);
        $this->assertSame($os->id, $json['minhas'][0]['id']);
        $this->assertSame($item->id, $json['minhas'][0]['checklist'][0]['id']);
        $this->assertCount(1, $json['os']);
    }

    public function test_csrf_informa_o_usuario_para_o_celular_enviar_so_as_proprias_acoes(): void
    {
        [, $tec] = $this->tenantComTecnico();

        $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($tec)->getJson('/colaborador/api/csrf')->assertOk()->assertJsonPath('usuario_id', $tec->id)->assertJsonStructure(['token']);
    }

    public function test_pacote_exige_login_e_celular(): void
    {
        $this->withHeaders(['User-Agent' => self::CELULAR])->getJson('/colaborador/api/pacote')->assertUnauthorized();
    }

    public function test_fluxo_completo_iniciar_dados_checklist_foto_assinaturas_e_concluir(): void
    {
        Storage::fake('public');
        [$t, $tec] = $this->tenantComTecnico();
        [, $os, $item] = $this->os($t, $tec);
        $png = 'data:image/png;base64,'.base64_encode('PNG');

        $r = $this->enviar($tec, [
            $this->acao('os_iniciar', ['os_id' => $os->id]),
            $this->acao('os_dados', ['os_id' => $os->id, 'horimetro_entry' => 1250.5, 'fuel_level' => '75', 'technical_notes' => 'Troquei o filtro']),
            $this->acao('os_checklist', ['os_id' => $os->id, 'item_id' => $item->id, 'status' => 'conforme', 'notas' => 'ok']),
            $this->acao('os_assinaturas', ['os_id' => $os->id, 'technician_signature' => $png, 'client_signature' => $png]),
            $this->acao('os_concluir', ['os_id' => $os->id]),
        ])->assertOk();

        $this->assertSame(['ok', 'ok', 'ok', 'ok', 'ok'], array_column($r->json('resultados'), 'status'));

        $os->refresh();
        $this->assertSame('Concluída', $os->status);
        $this->assertEquals(1250.5, (float) $os->horimetro_entry);
        $this->assertSame('75', (string) $os->fuel_level);
        $this->assertSame('Troquei o filtro', $os->technical_notes);
        $this->assertSame('conforme', $item->refresh()->status);
        $this->assertTrue((bool) $item->is_completed);
        $this->assertSame(['Em Andamento', 'Concluída'], $os->statusHistories()->orderBy('created_at')->pluck('new_status')->all());
    }

    public function test_foto_do_item_e_foto_geral_pelo_upload(): void
    {
        Storage::fake('public');
        [$t, $tec] = $this->tenantComTecnico();
        [, $os, $item] = $this->os($t, $tec);

        foreach ([['item_id' => $item->id], []] as $extra) {
            $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($tec)->post('/colaborador/api/fila', [
                'acao' => json_encode($this->acao('os_foto', ['os_id' => $os->id] + $extra)),
                'arquivo' => UploadedFile::fake()->image('foto.jpg'),
            ])->assertOk()->assertJsonPath('resultados.0.status', 'ok');
        }

        $this->assertSame(1, $item->refresh()->getMedia('photos')->count());
        $this->assertSame(1, $os->evidences()->count());
    }

    public function test_reenviar_a_mesma_acao_nao_aplica_duas_vezes(): void
    {
        [$t, $tec] = $this->tenantComTecnico();
        [, $os] = $this->os($t, $tec);
        $acao = $this->acao('os_iniciar', ['os_id' => $os->id]);

        $this->enviar($tec, [$acao])->assertOk();
        $os->refresh()->update(['status' => 'Pausada']);                     // alguém pausou entre os envios
        $r = $this->enviar($tec, [$acao])->assertOk();                        // celular reenviou (a rede caiu antes da resposta)

        $this->assertSame('ok', $r->json('resultados.0.status'));
        $this->assertSame('Pausada', $os->refresh()->status, 'a ação repetida não pode reiniciar a OS');
        $this->assertSame(1, ColabFilaProcessada::withoutGlobalScopes()->where('acao_uuid', $acao['uuid'])->count());
    }

    public function test_concluir_sem_horimetro_ou_assinaturas_e_recusado_e_os_segue_aberta(): void
    {
        [$t, $tec] = $this->tenantComTecnico();
        [, $os] = $this->os($t, $tec);

        $r = $this->enviar($tec, [$this->acao('os_concluir', ['os_id' => $os->id])])->assertOk();
        $this->assertSame('erro', $r->json('resultados.0.status'));
        $this->assertStringContainsString('horímetro', $r->json('resultados.0.mensagem'));

        $os->update(['horimetro_entry' => 10]);
        $r = $this->enviar($tec, [$this->acao('os_concluir', ['os_id' => $os->id])])->assertOk();
        $this->assertStringContainsString('assinaturas', $r->json('resultados.0.mensagem'));
        $this->assertSame('Aberto', $os->refresh()->status);
    }

    public function test_outro_tecnico_e_outro_tenant_nao_mexem_na_os(): void
    {
        [$t, $tec] = $this->tenantComTecnico();
        [, $os] = $this->os($t, $tec);
        [, $colega] = $this->tenantComTecnico($t);
        [, $estranho] = $this->tenantComTecnico();

        foreach ([$colega, $estranho] as $intruso) {
            $r = $this->enviar($intruso, [$this->acao('os_iniciar', ['os_id' => $os->id])])->assertOk();
            $this->assertSame('erro', $r->json('resultados.0.status'));
        }
        $this->assertSame('Aberto', $os->refresh()->status);
    }

    public function test_os_ja_encerrada_no_servidor_ignora_a_acao_com_aviso(): void
    {
        [$t, $tec] = $this->tenantComTecnico();
        [, $os] = $this->os($t, $tec, ['status' => 'Concluída']);

        $r = $this->enviar($tec, [$this->acao('os_dados', ['os_id' => $os->id, 'technical_notes' => 'tarde demais'])])->assertOk();

        $this->assertStringContainsString('encerrada', $r->json('resultados.0.mensagem'));
        $this->assertNull($os->refresh()->technical_notes);
    }

    public function test_dados_invalidos_sao_recusados(): void
    {
        [$t, $tec] = $this->tenantComTecnico();
        [, $os, $item] = $this->os($t, $tec);

        foreach ([
            $this->acao('os_dados', ['os_id' => $os->id, 'horimetro_entry' => 'abc']),
            $this->acao('os_dados', ['os_id' => $os->id, 'fuel_level' => '33']),
            $this->acao('os_checklist', ['os_id' => $os->id, 'item_id' => $item->id, 'status' => 'qualquer']),
            $this->acao('os_assinaturas', ['os_id' => $os->id, 'technician_signature' => 'javascript:alert(1)']),
            $this->acao('tipo_inexistente', ['os_id' => $os->id]),
            ['uuid' => 'nao-e-uuid', 'tipo' => 'os_iniciar', 'dados' => ['os_id' => $os->id]],
        ] as $acao) {
            $r = $this->enviar($tec, [$acao])->assertOk();
            $this->assertSame('erro', $r->json('resultados.0.status'), json_encode($acao['tipo']));
        }
        $this->assertSame('Aberto', $os->refresh()->status);
    }

    public function test_telas_do_app_carregam_o_script_offline_e_a_casca_da_execucao_abre(): void
    {
        [$t, $tec] = $this->tenantComTecnico();
        [, $os] = $this->os($t, $tec);

        $inicio = $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($tec)->get('/colaborador')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#/js/colab-offline\.js\?v=\d+#', $inicio);
        $this->assertStringContainsString('name="colab-base"', $inicio);
        $this->assertFileExists(public_path('js/colab-offline.js'));

        // A casca da execução offline abre online também (o conteúdo é montado no aparelho) e esconde o texto "sem conexão".
        $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($tec)->get('/colaborador/os/'.$os->id.'/offline')
            ->assertOk()->assertSee('id="colab-app"', false)->assertSee('display:none', false);

        // A tela de fallback (sem login) mostra o texto e o ponto onde o app monta o conteúdo.
        $this->withHeaders(['User-Agent' => self::CELULAR])->get('/colaborador/offline')->assertOk()->assertSee('id="colab-app"', false)->assertSee('Sem conexão');
    }
}
