<?php

namespace Tests\Feature;

use App\Filament\Resources\FrotaVinculoGpsResource\Pages\ListFrotaVinculosGps;
use App\Models\Asset;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaVinculoGps;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\GpsOdometroService;
use App\Services\Frota\PendenciasFrotaService;
use App\Support\TraccarService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Gestão de Frota, Fase 16: GPS (Traccar) alimentando o odômetro. O servidor Traccar é sempre simulado (Http::fake). */
class FrotaGpsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.traccar.url' => 'http://traccar.teste', 'services.traccar.email' => 'a@b.c', 'services.traccar.password' => 'x']);
        PendenciasFrotaService::esquecerPermissoes();
    }

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_frota_vinculos_gps']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function veiculo(Tenant $tenant): Asset
    {
        return Asset::create(['tenant_id' => $tenant->id, 'name' => 'Carro '.uniqid(), 'tag' => 'V-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'grupo' => Asset::GRUPO_VEICULO, 'placa' => 'ABC1D23', 'odometro_atual' => 10000]);
    }

    /** Simula o Traccar: dispositivo achado pelo identificador e distância (metros) por dia no resumo. */
    private function traccar(int $deviceId = 77, array $dias = [['distance' => 0]], bool $resumoFalha = false): void
    {
        Http::fake([
            'traccar.teste/api/devices*' => Http::response([['id' => $deviceId, 'uniqueId' => 'IMEI1']]),
            'traccar.teste/api/reports/summary*' => $resumoFalha ? Http::response('erro', 500) : Http::response($dias),
        ]);
    }

    private function erroEm(callable $fn, string $campo): void
    {
        try {
            $fn();
            $this->fail("Deveria recusar ({$campo}).");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($campo, $e->errors());
        }
    }

    private function vinculo(Asset $v, string $desde = '-3 hours'): FrotaVinculoGps
    {
        return FrotaVinculoGps::create(['tenant_id' => $v->tenant_id, 'ativo_id' => $v->id, 'traccar_device_id' => 77, 'identificador' => 'IMEI1', 'ultima_sincronizacao' => now()->modify($desde)]);
    }

    public function test_vincular_acha_o_dispositivo_e_recusa_duplicidade_e_identificador_desconhecido(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $s = app(GpsOdometroService::class);

        $this->traccar();
        $this->erroEm(fn () => $s->vincular($v, ' '), 'identificador');
        $vinculo = $s->vincular($v, 'IMEI1');
        $this->assertSame(77, $vinculo->traccar_device_id);
        $this->erroEm(fn () => $s->vincular($v, 'IMEI1'), 'ativo');
        $this->erroEm(fn () => $s->vincular($this->veiculo($tenant), 'IMEI1'), 'identificador');   // mesmo rastreador em outro veículo

        Http::fake(['traccar.teste/api/devices*' => Http::response([])]);
        $this->erroEm(fn () => $s->vincular($this->veiculo($tenant), 'NAO-EXISTE'), 'identificador');
        $this->erroEm(fn () => $s->vincular($this->veiculo($tenant)->forceFill(['grupo' => Asset::GRUPO_MAQUINA]), 'IMEI1'), 'ativo');
    }

    public function test_sincronizar_soma_os_km_do_gps_ao_odometro_e_guarda_a_fracao(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $vinc = $this->vinculo($v);
        Http::fake(['traccar.teste/api/reports/summary*' => Http::sequence()->push([['distance' => 12400], ['distance' => 30300]])->push([['distance' => 500]])]);   // 42,7 km, depois 0,5 km

        $r = app(GpsOdometroService::class)->sincronizar($vinc);

        $this->assertSame('aplicado', $r['status']);
        $this->assertSame(42, $r['km_adicionados']);
        $this->assertSame(10042, (int) $v->fresh()->odometro_atual);
        $this->assertSame('gps', FrotaLeituraOdometro::where('ativo_id', $v->id)->latest('lido_em')->value('origem'));
        $this->assertEqualsWithDelta(0.7, (float) $vinc->fresh()->km_acumulado, 0.001);

        // A fração de 0,7 km soma com os próximos 0,5 km e vira 1 km inteiro.
        $vinc->fresh()->update(['ultima_sincronizacao' => now()->subHour()]);
        $r2 = app(GpsOdometroService::class)->sincronizar($vinc->fresh());
        $this->assertSame(1, $r2['km_adicionados']);
        $this->assertSame(10043, (int) $v->fresh()->odometro_atual);
    }

    public function test_menos_de_1_km_nao_mexe_no_odometro_mas_guarda_e_avanca(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $vinc = $this->vinculo($v);
        $this->traccar(dias: [['distance' => 300]]);

        $r = app(GpsOdometroService::class)->sincronizar($vinc);

        $this->assertSame('sem_km', $r['status']);
        $this->assertSame(10000, (int) $v->fresh()->odometro_atual);
        $this->assertEqualsWithDelta(0.3, (float) $vinc->fresh()->km_acumulado, 0.001);
        $this->assertTrue($vinc->fresh()->ultima_sincronizacao->gt(now()->subMinute()));
    }

    public function test_falha_do_traccar_nao_perde_os_km_nao_avanca_a_data(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $vinc = $this->vinculo($v, '-5 hours');
        $antes = $vinc->ultima_sincronizacao;
        $this->traccar(resumoFalha: true);

        $r = app(GpsOdometroService::class)->sincronizar($vinc);

        $this->assertSame('falha', $r['status']);
        $this->assertSame(10000, (int) $v->fresh()->odometro_atual);
        $this->assertTrue($vinc->fresh()->ultima_sincronizacao->equalTo($antes));
    }

    public function test_distancia_absurda_e_ignorada(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $vinc = $this->vinculo($v);
        $this->traccar(dias: [['distance' => 5_000_000]]);   // 5.000 km numa sincronização

        $this->assertSame('falha', app(GpsOdometroService::class)->sincronizar($vinc)['status']);
        $this->assertSame(10000, (int) $v->fresh()->odometro_atual);
    }

    public function test_nao_conta_duas_vezes_o_trecho_que_o_motorista_ja_informou(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $vinc = $this->vinculo($v, '-6 hours');
        FrotaLeituraOdometro::registrar($v, 10100, 'checklist');   // o motorista informou 10.100 km agora
        $this->traccar(dias: [['distance' => 8000]]);

        app(GpsOdometroService::class)->sincronizar($vinc);

        // A consulta ao Traccar começa na hora da leitura manual, não 6 horas atrás.
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/api/reports/summary')) {
                return false;
            }
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $q);

            return strtotime($q['from']) > now()->subMinutes(5)->timestamp;
        });
    }

    public function test_veiculo_baixado_ou_vinculo_inativo_nao_sincroniza_e_atraso_vira_pendencia(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $vinc = $this->vinculo($v, '-3 days');
        $this->assertSame(72, GpsOdometroService::horasSemSincronizar($vinc));
        $p = (new PendenciasFrotaService)->doVeiculo($v)->firstWhere('categoria', 'gps');
        $this->assertStringContainsString('GPS sem sincronizar há 3 dia(s)', $p['mensagem']);

        app(GpsOdometroService::class)->desvincular($vinc);
        $this->assertSame('ignorado', app(GpsOdometroService::class)->sincronizar($vinc->fresh())['status']);
        $this->assertNull((new PendenciasFrotaService)->doVeiculo($v)->firstWhere('categoria', 'gps'));
    }

    public function test_comando_sincroniza_todos_e_sem_configuracao_do_traccar_nada_e_somado(): void
    {
        [$tenant, $admin] = $this->cliente();
        $v = $this->veiculo($tenant);
        $this->vinculo($v);
        $this->traccar(dias: [['distance' => 10000]]);

        $this->artisan('frota:sincronizar-gps')->expectsOutputToContain('1 veículo(s) atualizado(s) (+10 km)')->assertSuccessful();
        $this->assertSame(10010, (int) $v->fresh()->odometro_atual);

        config(['services.traccar.url' => null, 'services.traccar.email' => null]);
        $this->app->forgetInstance(TraccarService::class);
        $this->app->forgetInstance(GpsOdometroService::class);
        FrotaVinculoGps::query()->update(['ultima_sincronizacao' => now()->subHour()]);
        $this->artisan('frota:sincronizar-gps')->expectsOutputToContain('1 falha(s)')->assertSuccessful();
        $this->assertSame(10010, (int) $v->fresh()->odometro_atual);
    }

    public function test_um_cliente_nao_ve_o_do_outro_e_a_tela_funciona(): void
    {
        [$a, $adminA] = $this->cliente();
        $vA = $this->veiculo($a);
        [$b, $adminB] = $this->cliente();
        $this->traccar();

        $this->actingAs($adminA);
        Livewire::test(ListFrotaVinculosGps::class)->callTableAction('vincular', data: ['ativo_id' => $vA->id, 'identificador' => 'IMEI1'])->assertHasNoTableActionErrors();
        $vinc = FrotaVinculoGps::sole();
        Livewire::test(ListFrotaVinculosGps::class)->callTableAction('sincronizar', $vinc)->callTableAction('desvincular', $vinc);
        $this->assertFalse($vinc->fresh()->ativo);

        $this->actingAs($adminB);
        $this->assertSame(0, FrotaVinculoGps::count());
    }
}
