<?php

namespace Tests\Feature;

use App\Filament\Resources\FrotaBaixaResource\Pages\ListFrotaBaixas;
use App\Filament\Resources\FrotaLavagemResource\Pages\ListFrotaLavagens;
use App\Filament\Resources\FrotaPedagioResource\Pages\ListFrotaPedagios;
use App\Filament\Resources\FrotaTagResource\Pages\ListFrotaTags;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaBaixa;
use App\Models\FrotaChave;
use App\Models\FrotaLavagem;
use App\Models\FrotaPedagio;
use App\Models\FrotaTag;
use App\Models\FrotaVinculoMotorista;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\BaixaVeiculoService;
use App\Services\Frota\ChaveService;
use App\Services\Frota\CustoFrotaService;
use App\Services\Frota\LavagemService;
use App\Services\Frota\PedagioService;
use App\Services\Frota\PendenciasFrotaService;
use App\Services\Frota\SaidaVeiculoService;
use App\Services\Frota\VinculoMotoristaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Gestão de Frota, Fase 15: pedágio e tag, lavagem (com agenda) e baixa/venda do veículo. */
class FrotaOperacaoTest extends TestCase
{
    use DatabaseTransactions;

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_frota_tags', 'tabela_frota_pedagios', 'tabela_frota_lavagens', 'tabela_frota_baixas']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function veiculo(Tenant $tenant): Asset
    {
        return Asset::create(['tenant_id' => $tenant->id, 'name' => 'Carro '.uniqid(), 'tag' => 'V-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'grupo' => Asset::GRUPO_VEICULO, 'placa' => 'ABC1D23', 'odometro_atual' => 10000, 'acquisition_value' => 50000]);
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

    // ---- tag e pedágio ----

    public function test_tag_unica_por_numero_e_uma_ativa_por_veiculo(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $s = new PedagioService;

        $this->erroEm(fn () => $s->criarTag($v, ' ', 'sem_parar'), 'numero');
        $this->erroEm(fn () => $s->criarTag($v, '123', 'xyz'), 'operadora');
        $tag = $s->criarTag($v, 'ABC-123', 'sem_parar');
        $this->erroEm(fn () => $s->criarTag($this->veiculo($tenant), 'abc-123', 'veloe'), 'numero');
        $this->erroEm(fn () => $s->criarTag($v, 'OUTRA', 'veloe'), 'ativo');

        $s->cancelarTag($tag);
        $this->assertSame('OUTRA', $s->criarTag($v, 'OUTRA', 'veloe')->numero);
    }

    public function test_pedagio_usa_a_tag_ativa_sugere_o_motorista_pela_saida_e_valida(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $m = FleetDriver::create(['tenant_id' => $tenant->id, 'name' => 'João', 'cnh_expiry_date' => now()->addYear(), 'active' => true]);
        $s = new PedagioService;
        $tag = $s->criarTag($v, 'T1', 'conectcar');
        (new SaidaVeiculoService)->registrarSaida($v, ['finalidade' => 'cliente', 'motorista_id' => $m->id, 'destino' => 'X', 'motivo' => 'Y', 'odometro' => 10000, 'saida_em' => now()->subHours(6)->toDateTimeString()]);

        $p = $s->registrar($v, ['local' => 'Pedágio Anhanguera km 60', 'valor' => '12,60', 'passou_em' => now()->subHours(2)->toDateTimeString()]);

        $this->assertSame($tag->id, $p->tag_id);
        $this->assertSame($m->id, $p->motorista_id);
        $this->assertSame('12.60', $p->valor);
        $this->erroEm(fn () => $s->registrar($v, ['local' => ' ', 'valor' => 5]), 'local');
        $this->erroEm(fn () => $s->registrar($v, ['local' => 'X', 'valor' => 0]), 'valor');
        $this->erroEm(fn () => $s->registrar($v, ['local' => 'X', 'valor' => 5, 'passou_em' => now()->addDay()->toDateTimeString()]), 'passou_em');
    }

    // ---- lavagem ----

    public function test_lavagem_com_agenda_atrasada_vira_pendencia_e_nova_lavagem_zera(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $s = new LavagemService;

        $s->registrar($v, ['tipo' => 'completa', 'realizada_em' => now()->subDays(20)->toDateString(), 'intervalo_dias' => 15, 'valor' => '80,00']);
        $this->assertSame(5, LavagemService::diasAtrasada($v));
        $p = (new PendenciasFrotaService)->doVeiculo($v)->firstWhere('categoria', 'lavagem');
        $this->assertStringContainsString('atrasada há 5 dia(s)', $p['mensagem']);

        $s->registrar($v, ['tipo' => 'simples', 'realizada_em' => now()->toDateString(), 'intervalo_dias' => 15]);
        $this->assertNull(LavagemService::diasAtrasada($v));

        $s->registrar($v, ['tipo' => 'simples', 'realizada_em' => now()->toDateString()]);   // sem agenda: nada a cobrar
        $this->assertNull(LavagemService::diasAtrasada($v));
    }

    public function test_lavagem_valida_tipo_data_valor_e_intervalo(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $s = new LavagemService;

        $this->erroEm(fn () => $s->registrar($v, ['tipo' => 'x']), 'tipo');
        $this->erroEm(fn () => $s->registrar($v, ['tipo' => 'simples', 'realizada_em' => now()->addDay()->toDateString()]), 'realizada_em');
        $this->erroEm(fn () => $s->registrar($v, ['tipo' => 'simples', 'valor' => -1]), 'valor');
        $this->erroEm(fn () => $s->registrar($v, ['tipo' => 'simples', 'intervalo_dias' => 400]), 'intervalo_dias');
    }

    // ---- baixa ----

    public function test_baixa_tira_o_veiculo_das_listas_e_das_pendencias_e_bloqueia_novos_registros(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $v->update(['ipva_vencimento' => now()->subDays(5)]);
        $this->assertTrue(isset(Asset::opcoesVeiculos()[$v->id]));
        $this->assertGreaterThan(0, (new PendenciasFrotaService)->todas()->where('ativo_id', $v->id)->count());

        $b = (new BaixaVeiculoService)->registrar($v, ['tipo' => 'venda', 'valor' => '60000', 'comprador' => 'Fulano', 'motivo' => 'Renovação da frota']);

        $this->assertFalse(isset(Asset::opcoesVeiculos()[$v->id]));
        $this->assertSame(0, (new PendenciasFrotaService)->todas()->where('ativo_id', $v->id)->count());
        $this->assertSame($b->id, $v->baixaVigente()->id);
        $m = FleetDriver::create(['tenant_id' => $tenant->id, 'name' => 'João', 'cnh_expiry_date' => now()->addYear(), 'active' => true]);
        $this->erroEm(fn () => (new SaidaVeiculoService)->registrarSaida($v, ['finalidade' => 'outro', 'motorista_id' => $m->id, 'destino' => 'X', 'motivo' => 'Y', 'odometro' => 10000]), 'ativo');
        $this->erroEm(fn () => (new PedagioService)->registrar($v, ['local' => 'X', 'valor' => 5]), 'ativo');
        $this->erroEm(fn () => (new LavagemService)->registrar($v, ['tipo' => 'simples']), 'ativo');
        $this->erroEm(fn () => (new BaixaVeiculoService)->registrar($v, ['tipo' => 'venda', 'valor' => 1, 'motivo' => 'x']), 'ativo');
    }

    public function test_baixa_encerra_titular_desativa_chaves_e_registra_o_odometro_final(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $m = FleetDriver::create(['tenant_id' => $tenant->id, 'name' => 'Ana', 'cnh_expiry_date' => now()->addYear(), 'active' => true]);
        (new VinculoMotoristaService)->atribuir($v, $m->id, now()->subDays(30)->toDateString());
        (new ChaveService)->criar($v, 'Principal');

        (new BaixaVeiculoService)->registrar($v, ['tipo' => 'sucata', 'motivo' => 'Fim da vida útil', 'odometro_final' => 10500]);

        $this->assertNull(VinculoMotoristaService::titular($v));
        $this->assertSame(0, FrotaChave::where('ativo_id', $v->id)->where('ativo', true)->count());
        $this->assertSame(10500, (int) $v->fresh()->odometro_atual);
    }

    public function test_baixa_recusa_veiculo_fora_chave_fora_venda_sem_valor_e_data_futura(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $s = new BaixaVeiculoService;
        $m = FleetDriver::create(['tenant_id' => $tenant->id, 'name' => 'Ana', 'cnh_expiry_date' => now()->addYear(), 'active' => true]);

        $this->erroEm(fn () => $s->registrar($v, ['tipo' => 'x', 'motivo' => 'a']), 'tipo');
        $this->erroEm(fn () => $s->registrar($v, ['tipo' => 'sucata', 'motivo' => ' ']), 'motivo');
        $this->erroEm(fn () => $s->registrar($v, ['tipo' => 'venda', 'motivo' => 'a']), 'valor');
        $this->erroEm(fn () => $s->registrar($v, ['tipo' => 'sucata', 'motivo' => 'a', 'data' => now()->addDay()->toDateString()]), 'data');

        $saida = (new SaidaVeiculoService)->registrarSaida($v, ['finalidade' => 'cliente', 'motorista_id' => $m->id, 'destino' => 'X', 'motivo' => 'Y', 'odometro' => 10000]);
        $this->erroEm(fn () => $s->registrar($v, ['tipo' => 'sucata', 'motivo' => 'a']), 'ativo');
        (new SaidaVeiculoService)->registrarEntrada($saida, ['odometro' => 10010]);

        $chave = (new ChaveService)->criar($v, 'Principal');
        (new ChaveService)->entregar($chave, ['responsavel_nome' => 'Zé', 'motivo' => 'Oficina']);
        $this->erroEm(fn () => $s->registrar($v, ['tipo' => 'sucata', 'motivo' => 'a']), 'ativo');
        $this->assertSame(0, FrotaBaixa::count());
    }

    public function test_reverter_baixa_devolve_o_veiculo_as_listas(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $s = new BaixaVeiculoService;
        $b = $s->registrar($v, ['tipo' => 'sucata', 'motivo' => 'x']);

        $this->erroEm(fn () => $s->reverter($b, ' '), 'motivo');
        $s->reverter($b, 'Venda cancelada pelo comprador');
        $this->assertTrue(isset(Asset::opcoesVeiculos()[$v->id]));
        $this->assertNull($v->baixaVigente());
        $this->erroEm(fn () => $s->reverter($b->fresh(), 'de novo'), 'baixa');
        $this->assertSame('venda', $s->registrar($v->fresh(), ['tipo' => 'venda', 'valor' => 40000, 'motivo' => 'Vendido depois'])->tipo);
    }

    // ---- custo e telas ----

    public function test_pedagios_e_lavagens_entram_no_custo_por_veiculo(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        (new PedagioService)->registrar($v, ['local' => 'A', 'valor' => 12.5, 'passou_em' => now()->subDay()->toDateTimeString()]);
        (new PedagioService)->registrar($v, ['local' => 'B', 'valor' => 7.5, 'passou_em' => now()->subDay()->toDateTimeString()]);
        (new LavagemService)->registrar($v, ['tipo' => 'completa', 'valor' => 80]);

        $c = (new CustoFrotaService)->veiculo($v, 3)['componentes'];

        $this->assertSame(20.0, $c['pedagios']);
        $this->assertSame(80.0, $c['lavagens']);
    }

    public function test_um_cliente_nao_ve_o_do_outro_e_as_telas_funcionam(): void
    {
        [$a, $adminA] = $this->cliente();
        $vA = $this->veiculo($a);
        [$b, $adminB] = $this->cliente();

        $this->actingAs($adminA);
        Livewire::test(ListFrotaTags::class)->callTableAction('nova_tag', data: ['ativo_id' => $vA->id, 'numero' => 'TG1', 'operadora' => 'veloe'])->assertHasNoTableActionErrors();
        Livewire::test(ListFrotaPedagios::class)->callTableAction('registrar_pedagio', data: ['ativo_id' => $vA->id, 'passou_em' => now()->toDateTimeString(), 'local' => 'Praça 1', 'valor' => 9.9])->assertHasNoTableActionErrors();
        Livewire::test(ListFrotaLavagens::class)->callTableAction('registrar_lavagem', data: ['ativo_id' => $vA->id, 'realizada_em' => now()->toDateString(), 'tipo' => 'simples', 'intervalo_dias' => 10])->assertHasNoTableActionErrors();
        Livewire::test(ListFrotaBaixas::class)->callTableAction('registrar_baixa', data: ['ativo_id' => $vA->id, 'tipo' => 'venda', 'data' => now()->toDateString(), 'valor' => 45000, 'motivo' => 'Troca de frota'])->assertHasNoTableActionErrors();
        $this->assertSame(1, FrotaTag::count() + FrotaPedagio::count() + FrotaLavagem::count() + FrotaBaixa::count() - 3);
        $baixa = FrotaBaixa::sole();
        Livewire::test(ListFrotaBaixas::class)->assertSee('Baixado')->callTableAction('reverter', $baixa, data: ['motivo' => 'Engano']);
        $this->assertNull($vA->baixaVigente());

        $this->actingAs($adminB);
        $this->assertSame(0, FrotaTag::count() + FrotaPedagio::count() + FrotaLavagem::count() + FrotaBaixa::count() + FrotaVinculoMotorista::count());
    }
}
