<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\EditAsset;
use App\Filament\Resources\AssetResource\RelationManagers\PneusRelationManager;
use App\Filament\Resources\FrotaPneuResource\Pages\ListFrotaPneus;
use App\Models\Asset;
use App\Models\FrotaChecklist;
use App\Models\FrotaInspecaoPneu;
use App\Models\FrotaInstalacaoComponente;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaModeloChecklist;
use App\Models\FrotaPneu;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\ChecklistFrotaService;
use App\Services\Frota\ModelosChecklistPadrao;
use App\Services\Frota\PneuService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Gestão de Frota, Fase 2: pneus (montagem, rodízio, remoção, recapagem, km, custo por km e alertas). */
class FrotaPneuTest extends TestCase
{
    use DatabaseTransactions;

    private PneuService $servico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servico = new PneuService;
    }

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_frota_pneus', 'tabela_frota_checklists', 'tabela_frota_modelos_checklist']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function veiculo(Tenant $tenant, array $extra = []): Asset
    {
        return Asset::create(array_merge(['tenant_id' => $tenant->id, 'name' => 'Caminhão '.uniqid(), 'tag' => 'V-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'grupo' => Asset::GRUPO_VEICULO, 'veiculo_pesado' => true, 'placa' => 'ABC1D23', 'odometro_atual' => 10000], $extra));
    }

    private function pneu(Tenant $tenant, array $extra = []): FrotaPneu
    {
        return FrotaPneu::create(array_merge(['tenant_id' => $tenant->id, 'numero_fogo' => 'F'.uniqid(), 'marca' => 'Pirelli', 'medida' => '295/80 R22.5', 'custo' => 2000], $extra));
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

    public function test_mounting_puts_the_tire_in_the_position_and_updates_the_odometer(): void
    {
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $pneu = $this->pneu($tenant);

        $inst = $this->servico->montar($pneu, $ativo, 'E1-LE', 10500, $admin);

        $this->assertSame(FrotaPneu::MONTADO, $pneu->fresh()->situacao);
        $this->assertSame('E1-LE', $inst->posicao);
        $this->assertSame('pneu', $inst->componente_type);
        $this->assertSame(10500.0, (float) $ativo->fresh()->odometro_atual);
        $this->assertSame('pneu', FrotaLeituraOdometro::withoutGlobalScopes()->where('ativo_id', $ativo->id)->sole()->origem);
        $this->assertSame($pneu->id, $ativo->pneusMontados()->sole()->componente_id);
    }

    public function test_mounting_rules(): void
    {
        [$tenant, $admin] = $this->cliente();
        $pesado = $this->veiculo($tenant);
        $leve = $this->veiculo($tenant, ['veiculo_pesado' => false, 'placa' => 'XYZ9A87']);
        $maquina = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador', 'tag' => 'G-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL]);
        $a = $this->pneu($tenant);
        $b = $this->pneu($tenant);

        $this->servico->montar($a, $pesado, 'E1-LE', 10000, $admin);

        $this->erroEm(fn () => $this->servico->montar($b, $pesado, 'E1-LE', 10000), 'posicao');      // ocupada
        $this->erroEm(fn () => $this->servico->montar($b, $pesado, 'XX-99', 10000), 'posicao');      // inexistente
        $this->erroEm(fn () => $this->servico->montar($b, $leve, 'E3-LEE', 10000), 'posicao');       // leve não tem eixo 3
        $this->erroEm(fn () => $this->servico->montar($a, $pesado, 'E1-LD', 10000), 'pneu');         // já montado
        $this->erroEm(fn () => $this->servico->montar($b, $maquina, 'E1-LE', 10000), 'ativo');       // não é veículo
        $this->servico->montar($b, $pesado, 'ESTEPE', 10000);                                        // estepe vale
        $this->assertSame(2, $pesado->pneusMontados()->count());
    }

    public function test_the_database_itself_refuses_two_open_installations_of_the_same_tire_or_position(): void
    {
        [$tenant] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $a = $this->pneu($tenant);
        $b = $this->pneu($tenant);
        $nova = fn (FrotaPneu $p, string $pos) => FrotaInstalacaoComponente::create([
            'tenant_id' => $tenant->id, 'ativo_id' => $ativo->id, 'componente_type' => 'pneu', 'componente_id' => $p->id,
            'posicao' => $pos, 'instalado_em' => now(), 'odometro_instalacao' => 1,
        ]);

        $nova($a, 'E1-LE');

        // mesmo pneu, outra posição: recusado
        try {
            DB::transaction(fn () => $nova($a, 'E1-LD'));
            $this->fail('Mesmo pneu aberto duas vezes.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        // outro pneu na mesma posição: recusado
        $this->expectException(QueryException::class);
        $nova($b, 'E1-LE');
    }

    public function test_rotation_moves_or_swaps_tires(): void
    {
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $a = $this->pneu($tenant);
        $b = $this->pneu($tenant);
        $this->servico->montar($a, $ativo, 'E1-LE', 10000, $admin);
        $this->servico->montar($b, $ativo, 'E1-LD', 10000, $admin);

        // para posição livre
        $this->servico->rodizio($a, 'E2-LEE', 10300, $admin);
        $this->assertSame('E2-LEE', $a->instalacaoAberta()->first()->posicao);
        $this->assertSame(2, $a->instalacoes()->count());
        $this->assertSame('rodizio', $a->instalacoes()->whereNotNull('removido_em')->first()->motivo_remocao);

        // para posição ocupada: troca
        $this->servico->rodizio($a, 'E1-LD', 10400, $admin);
        $this->assertSame('E1-LD', $a->instalacaoAberta()->first()->posicao);
        $this->assertSame('E2-LEE', $b->instalacaoAberta()->first()->posicao);   // o outro foi para onde o primeiro estava
        $this->assertSame(2, $ativo->pneusMontados()->count());

        $this->erroEm(fn () => $this->servico->rodizio($a, 'E1-LD', 10500), 'posicao');   // já está lá
        $this->erroEm(fn () => $this->servico->rodizio($a, 'NAO-EXISTE', 10500), 'posicao');
    }

    public function test_removal_sends_the_tire_to_the_right_place_and_validates_the_odometer(): void
    {
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $mont = fn (string $pos) => tap($this->pneu($tenant), fn ($p) => $this->servico->montar($p, $ativo, $pos, 10000, $admin));

        $desgaste = $mont('E1-LE');
        $recapar = $mont('E1-LD');
        $sucata = $mont('E2-LEE');

        $this->servico->remover($desgaste, 'desgaste', 12000, $admin);
        $this->servico->remover($recapar, 'recapagem', 12000, $admin);
        $this->servico->remover($sucata, 'descarte', 12000, $admin);

        $this->assertSame(FrotaPneu::ESTOQUE, $desgaste->fresh()->situacao);
        $this->assertSame(FrotaPneu::EM_RECAPAGEM, $recapar->fresh()->situacao);
        $this->assertSame(FrotaPneu::SUCATEADO, $sucata->fresh()->situacao);
        $this->assertSame(12000, $desgaste->instalacoes()->first()->odometro_remocao);
        $this->assertSame(0, $ativo->pneusMontados()->count());

        $this->erroEm(fn () => $this->servico->remover($desgaste->fresh(), 'desgaste', 12000), 'pneu');   // não está montado
        $outro = $mont('E1-LE');
        $this->erroEm(fn () => $this->servico->remover($outro, 'desgaste', 5), 'odometro');              // menor que a montagem
        $this->erroEm(fn () => $this->servico->remover($outro, 'inventado', 13000), 'motivo');
    }

    public function test_retread_cycle_keeps_history_and_distance_per_tire(): void
    {
        [$tenant, $admin] = $this->cliente();
        $v1 = $this->veiculo($tenant);
        $v2 = $this->veiculo($tenant, ['placa' => 'QWE1R23', 'odometro_atual' => 50000]);
        $pneu = $this->pneu($tenant, ['custo' => 2000]);

        $this->servico->montar($pneu, $v1, 'E1-LE', 10000, $admin);
        $this->servico->remover($pneu, 'recapagem', 15000, $admin);
        $this->assertSame(FrotaPneu::EM_RECAPAGEM, $pneu->fresh()->situacao);

        $this->erroEm(fn () => $this->servico->montar($pneu->fresh(), $v2, 'E1-LE', 50000), 'pneu');       // em recapagem não monta

        $this->servico->concluirRecapagem($pneu->fresh(), 500.0, 14.0);
        $pneu->refresh();
        $this->assertSame(FrotaPneu::VIDA_RECAPADO_1, $pneu->vida);
        $this->assertSame(FrotaPneu::ESTOQUE, $pneu->situacao);
        $this->assertSame('2500.00', $pneu->custo);

        // volta em OUTRO veículo
        $this->servico->montar($pneu, $v2, 'E2-LEE', 50000, $admin);
        $v2->update(['odometro_atual' => 51000]);   // roda 1.000 km com ele montado

        $pneu = FrotaPneu::find($pneu->id);
        $this->assertSame(2, $pneu->instalacoes()->count());
        $this->assertSame(6000, $pneu->kmRodado());                      // 5.000 + 1.000
        $this->assertSame(0.4167, $pneu->custoPorKm());                   // 2.500 ÷ 6.000

        // limites da recapagem
        $this->erroEm(fn () => $this->servico->concluirRecapagem($pneu), 'pneu');                          // não está em recapagem
        $velho = $this->pneu($tenant, ['vida' => FrotaPneu::VIDA_RECAPADO_3, 'situacao' => FrotaPneu::EM_RECAPAGEM]);
        $this->erroEm(fn () => $this->servico->concluirRecapagem($velho), 'pneu');                         // 3 recapagens: sucata
    }

    public function test_alerts_for_tread_age_pressure_and_rotation(): void
    {
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $pneu = $this->pneu($tenant, ['dot' => '0115', 'pressao_min_psi' => 100, 'pressao_max_psi' => 120]);   // fabricado em 2015
        $this->servico->montar($pneu, $ativo, 'E1-LE', 10000, $admin);

        $tipos = fn () => collect(FrotaPneu::find($pneu->id)->alertas())->keyBy('tipo');

        $this->assertTrue($tipos()->has('idade'));
        $this->assertFalse($tipos()->has('sulco'));

        $this->servico->registrarInspecao($pneu, 2.9, 95.0);
        $this->assertSame('atencao', $tipos()['sulco']['gravidade']);
        $this->assertTrue($tipos()->has('pressao'));

        $this->servico->registrarInspecao($pneu, 1.5, 110.0);
        $this->assertSame('critica', $tipos()['sulco']['gravidade']);
        $this->assertFalse($tipos()->has('pressao'));

        $this->servico->registrarInspecao($pneu, 8.0, 110.0);
        $this->assertFalse($tipos()->has('sulco'));

        $this->assertFalse($tipos()->has('rodizio'));
        $ativo->update(['odometro_atual' => 20500]);            // 10.500 km na mesma posição
        $this->assertTrue($tipos()->has('rodizio'));

        // estepe não pede rodízio; sucateado não alerta
        $this->servico->rodizio($pneu, 'ESTEPE', 20500, $admin);
        $this->assertFalse($tipos()->has('rodizio'));
        $pneu->update(['situacao' => FrotaPneu::SUCATEADO]);
        $this->assertSame([], FrotaPneu::find($pneu->id)->alertas());

        $this->erroEm(fn () => $this->servico->registrarInspecao($pneu, null, null), 'sulco_mm');
        $this->assertNull($this->pneu($tenant, ['dot' => '9999'])->idadeAnos());
    }

    public function test_a_tire_in_stock_can_be_measured_without_a_vehicle(): void
    {
        [$tenant] = $this->cliente();
        $pneu = $this->pneu($tenant, ['pressao_min_psi' => 100, 'pressao_max_psi' => 120]);

        $inspecao = $this->servico->registrarInspecao($pneu, 9.0, 110.0);

        $this->assertNull($inspecao->ativo_id);
        $this->assertSame('9.0', (string) $inspecao->sulco_mm);
        $this->assertSame([], FrotaPneu::find($pneu->id)->alertas());
    }

    public function test_the_tread_from_a_full_checklist_becomes_a_vehicle_inspection(): void
    {
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        ModelosChecklistPadrao::garantir($tenant->id);
        $completo = FrotaModeloChecklist::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('tipo', 'completo')->with('itens')->firstOrFail();

        (new ChecklistFrotaService)->registrar($ativo, [
            'tipo' => FrotaChecklist::TIPO_SAIDA, 'modelo_id' => $completo->id, 'odometro' => 10100,
            'respostas' => $completo->itens->mapWithKeys(fn ($i) => [$i->id => ['resultado' => 'ok', 'valor' => $i->descricao === 'Sulco dos pneus' ? '2,5' : ($i->unidade ? '12' : null)]])->all(),
        ], $admin);

        $inspecao = FrotaInspecaoPneu::where('ativo_id', $ativo->id)->sole();
        $this->assertNull($inspecao->pneu_id);
        $this->assertSame('2.5', (string) $inspecao->sulco_mm);
        $this->assertSame(2.5, $ativo->ultimoSulcoMm());
    }

    public function test_clients_do_not_see_each_others_tires_and_the_fire_number_is_unique_per_client(): void
    {
        [$a, $adminA] = $this->cliente();
        [$b, $adminB] = $this->cliente();
        $this->pneu($a, ['numero_fogo' => 'F-100']);
        $this->pneu($b, ['numero_fogo' => 'F-100']);       // mesmo número em outro cliente: pode

        $this->actingAs($adminB);
        $this->assertSame(1, FrotaPneu::count());

        $this->expectException(QueryException::class);
        $this->pneu($a, ['numero_fogo' => 'F-100']);        // repetido no mesmo cliente: não
    }

    public function test_screens_work_for_the_admin(): void
    {
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $pneu = $this->pneu($tenant);
        $this->actingAs($admin);

        Livewire::test(ListFrotaPneus::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$pneu])
            ->callTableAction('montar', $pneu, ['ativo_id' => $ativo->id, 'posicao' => 'E1-LE', 'odometro' => 10200]);
        $this->assertSame(FrotaPneu::MONTADO, $pneu->fresh()->situacao);

        Livewire::test(ListFrotaPneus::class)
            ->callTableAction('remover', $pneu->fresh(), ['motivo' => 'desgaste', 'odometro' => 11000]);
        $this->assertSame(FrotaPneu::ESTOQUE, $pneu->fresh()->situacao);

        // aba "Pneus" na ficha do veículo (e escondida para máquina)
        Livewire::test(PneusRelationManager::class, ['ownerRecord' => $ativo, 'pageClass' => EditAsset::class])->assertSuccessful();
        $maquina = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador', 'tag' => 'G-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL]);
        $this->assertFalse(PneusRelationManager::canViewForRecord($maquina, EditAsset::class));
        $this->assertTrue(PneusRelationManager::canViewForRecord($ativo, EditAsset::class));
    }
}
