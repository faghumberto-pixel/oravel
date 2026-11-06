<?php

namespace Tests\Feature;

use App\Filament\Pages\LocalizacaoEquipamentos;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Demo\LocadoraDemoService;
use App\Services\Demo\RastreadorSimulado;
use App\Services\LocalizacaoEquipamentosService;
use App\Support\TraccarService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** Tela "Localizacao dos Equipamentos": obra do contrato ativo, rastreador (simulado na demo) e fallbacks. */
class LocalizacaoEquipamentosTest extends TestCase
{
    use DatabaseTransactions;

    private function entrarNaDemo(): void
    {
        app(LocadoraDemoService::class)->criar('x');
        $this->actingAs(User::withoutGlobalScopes()->where('email', LocadoraDemoService::EMAIL_ADMIN)->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function tenantReal(): array
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', LocalizacaoEquipamentosService::FEATURE]]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'loc-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'A', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return [$tenant, $plan];
    }

    private function contratoAtivo(Tenant $t, Asset $a, array $local): void
    {
        $clienteId = (string) Str::uuid();
        DB::table('clients')->insert(['id' => $clienteId, 'tenant_id' => $t->id, 'name' => 'Cliente Mapa', 'address' => 'Rua do Cliente, 10', 'latitude' => -23.1, 'longitude' => -46.9, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('contracts')->insert(array_merge([
            'id' => (string) Str::uuid(), 'tenant_id' => $t->id, 'client_id' => $clienteId, 'asset_id' => $a->id, 'contract_number' => 'MP-'.uniqid(),
            'start_date' => now()->subMonth(), 'end_date' => now()->addMonth(), 'status' => 'Ativo', 'is_active' => true, 'price' => 1000,
            'created_at' => now(), 'updated_at' => now(),
        ], $local));
    }

    public function test_demo_resolve_31_pontos_com_contrato_unidade_e_rastreador_simulado(): void
    {
        $this->entrarNaDemo();
        Carbon::setTestNow(Carbon::parse('2026-11-09 10:00:00'));

        $r = app(LocalizacaoEquipamentosService::class)->pontos();
        $pontos = collect($r['pontos']);

        $this->assertTrue($r['simulado']);
        $this->assertSame(0, $r['sem_localizacao']);
        $this->assertCount(31, $pontos); // 25 equipamentos + 6 veiculos da frota (no patio da matriz)
        foreach ($pontos as $p) {
            $this->assertGreaterThan(-25.5, $p['lat']);
            $this->assertLessThan(-20.5, $p['lat']);
            $this->assertGreaterThan(-50, $p['lng']);
            $this->assertLessThan(-45, $p['lng']);
        }

        // 13 locados: 5 com rastreador simulado e 8 pela obra do contrato; os demais (12 equipamentos + 6 veículos), pela unidade.
        $this->assertSame(5, $pontos->where('rastreador', true)->count());
        $this->assertSame(['C-01', 'G-03', 'PA-01', 'PT-02', 'PT-05'], $pontos->where('rastreador', true)->pluck('patrimonio')->sort()->values()->all());
        $this->assertSame(8, $pontos->where('fonte', 'contrato')->count());
        $this->assertSame(18, $pontos->where('fonte', 'unidade')->count());
        $this->assertStringContainsString('simulado', $pontos->firstWhere('patrimonio', 'PT-05')['fonte_rotulo']);

        // Em deslocamento: tem trilha e velocidade; parado: sem trilha.
        $movendo = $pontos->firstWhere('patrimonio', 'PT-05');
        $parado = $pontos->firstWhere('patrimonio', 'G-03');
        $this->assertGreaterThan(5, count($movendo['trilha']));
        $this->assertGreaterThan(0, $movendo['velocidade']);
        $this->assertSame([], $parado['trilha']);
        $this->assertSame(0.0, $parado['velocidade']);

        // O rastreador anda com o tempo (mesmo equipamento, 12 minutos depois, outra posicao).
        Carbon::setTestNow(Carbon::parse('2026-11-09 10:12:00'));
        $depois = collect(app(LocalizacaoEquipamentosService::class)->pontos()['pontos'])->firstWhere('patrimonio', 'PT-05');
        $this->assertNotSame($movendo['lat'], $depois['lat']);

        // Traz custo e sinalizacao para o popup.
        $this->assertSame('trocar_vender', $pontos->firstWhere('patrimonio', 'G-03')['situacao']['chave']);
        $this->assertGreaterThan(0, $pontos->firstWhere('patrimonio', 'G-03')['custo_total']);
        Carbon::setTestNow();
    }

    public function test_rastreador_simulado_e_deterministico_e_so_a_demo_usa(): void
    {
        $sim = new RastreadorSimulado;
        $t = Carbon::parse('2026-11-09 10:00:00');

        $this->assertSame($sim->posicao('PA-01', -23.0, -47.0, $t), $sim->posicao('PA-01', -23.0, -47.0, $t));
        $this->assertTrue(RastreadorSimulado::tem('PA-01'));
        $this->assertFalse(RastreadorSimulado::tem('XX-99'));

        // Tenant real (nao demo) nao ganha rastreador simulado.
        [$tenant] = $this->tenantReal();
        $a = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo PA-01', 'patrimonio' => 'PA-01', 'tag' => 'R-'.uniqid(), 'status' => 'locado']);
        $this->contratoAtivo($tenant, $a, ['local_tipo' => 'outro', 'latitude_obra' => -23.5, 'longitude_obra' => -46.6, 'endereco_obra' => 'Obra X', 'cidade_obra' => 'São Paulo', 'uf_obra' => 'SP']);

        $r = app(LocalizacaoEquipamentosService::class)->pontos();
        $this->assertFalse($r['simulado']);
        $this->assertFalse($r['pontos'][0]['rastreador']);
        $this->assertSame('contrato', $r['pontos'][0]['fonte']);
        $this->assertEqualsWithDelta(-23.5, $r['pontos'][0]['lat'], 0.0001);
    }

    public function test_tenant_real_usa_o_traccar_quando_ha_vinculo_e_cai_no_contrato_se_ele_falhar(): void
    {
        [$tenant] = $this->tenantReal();
        $a = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Caminhão Rastreado', 'patrimonio' => 'CAM-1', 'tag' => 'R-'.uniqid(), 'status' => 'locado']);
        $this->contratoAtivo($tenant, $a, ['local_tipo' => 'outro', 'latitude_obra' => -23.5, 'longitude_obra' => -46.6, 'endereco_obra' => 'Obra Y', 'cidade_obra' => 'São Paulo', 'uf_obra' => 'SP']);
        DB::table('frota_vinculos_gps')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'ativo_id' => $a->id, 'traccar_device_id' => 777, 'identificador' => 'GPS-777', 'km_acumulado' => 0, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->mock(TraccarService::class, fn ($m) => $m->shouldReceive('getPositions')->once()->andReturn([['deviceId' => 777, 'latitude' => -22.0, 'longitude' => -47.5, 'speed' => 10, 'fixTime' => '2026-11-09T12:00:00Z']]));
        $p = app(LocalizacaoEquipamentosService::class)->pontos()['pontos'][0];
        $this->assertTrue($p['rastreador']);
        $this->assertSame('rastreador', $p['fonte']);
        $this->assertEqualsWithDelta(-22.0, $p['lat'], 0.0001);
        $this->assertSame(18.5, $p['velocidade'], 'Traccar manda em nos: 10 nos = 18,5 km/h');

        $this->mock(TraccarService::class, fn ($m) => $m->shouldReceive('getPositions')->once()->andThrow(new \RuntimeException('Traccar fora do ar')));
        $p = app(LocalizacaoEquipamentosService::class)->pontos()['pontos'][0];
        $this->assertFalse($p['rastreador']);
        $this->assertSame('contrato', $p['fonte']);
        $this->assertEqualsWithDelta(-23.5, $p['lat'], 0.0001);
    }

    public function test_locais_do_contrato_sede_cliente_e_sem_localizacao(): void
    {
        [$tenant] = $this->tenantReal();
        $cliente = Asset::create(['tenant_id' => $tenant->id, 'name' => 'No Cliente', 'tag' => 'R-'.uniqid(), 'status' => 'locado']);
        $this->contratoAtivo($tenant, $cliente, ['local_tipo' => 'endereco_cliente']);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'Perdido', 'tag' => 'R-'.uniqid(), 'status' => 'disponivel']);

        $r = app(LocalizacaoEquipamentosService::class)->pontos();

        $this->assertSame(1, $r['sem_localizacao']);
        $this->assertCount(1, $r['pontos']);
        $this->assertSame('contrato', $r['pontos'][0]['fonte']);
        $this->assertEqualsWithDelta(-23.1, $r['pontos'][0]['lat'], 0.0001);
        $this->assertStringContainsString('Rua do Cliente', $r['pontos'][0]['local']);
    }

    public function test_pagina_so_abre_com_a_chave_e_atualiza_o_mapa(): void
    {
        [$tenant, $plan] = $this->tenantReal();
        $plan->update(['features' => ['tabela_assets']]);
        auth()->user()->unsetRelation('tenant');

        $this->assertFalse(LocalizacaoEquipamentos::canAccess());
        $this->get('/admin/localizacao-equipamentos')->assertForbidden();

        $plan->update(['features' => ['tabela_assets', LocalizacaoEquipamentosService::FEATURE]]);
        auth()->user()->unsetRelation('tenant');

        $this->assertTrue(LocalizacaoEquipamentos::canAccess());
        $this->get('/admin/localizacao-equipamentos')->assertOk()->assertSee('mapaEquipamentos', false);

        Livewire::test(LocalizacaoEquipamentos::class)->call('atualizar')->assertDispatched('pontos-atualizados');
    }
}
