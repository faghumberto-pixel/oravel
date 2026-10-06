<?php

namespace Tests\Feature;

use App\Filament\Pages\FrotaBateriasPorVeiculo;
use App\Filament\Resources\AssetResource\Pages\EditAsset;
use App\Filament\Resources\AssetResource\RelationManagers\BateriasRelationManager;
use App\Filament\Resources\FrotaBateriaResource\Pages\ListFrotaBaterias;
use App\Models\Asset;
use App\Models\FrotaBateria;
use App\Models\FrotaChecklist;
use App\Models\FrotaInstalacaoComponente;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaModeloChecklist;
use App\Models\FrotaTesteBateria;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\BateriaService;
use App\Services\Frota\ChecklistFrotaService;
use App\Services\Frota\ModelosChecklistPadrao;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Gestão de Frota, Fase 3: baterias (instalação, remoção, idade, garantia, tensão e relatório por veículo). */
class FrotaBateriaTest extends TestCase
{
    use DatabaseTransactions;

    private BateriaService $servico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servico = new BateriaService;
    }

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_frota_baterias', 'tabela_frota_pneus', 'tabela_frota_checklists', 'tabela_frota_modelos_checklist']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function veiculo(Tenant $tenant, array $extra = []): Asset
    {
        return Asset::create(array_merge(['tenant_id' => $tenant->id, 'name' => 'Caminhão '.uniqid(), 'tag' => 'V-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'grupo' => Asset::GRUPO_VEICULO, 'placa' => 'ABC1D23', 'odometro_atual' => 10000], $extra));
    }

    private function bateria(Tenant $tenant, array $extra = []): FrotaBateria
    {
        return FrotaBateria::create(array_merge(['tenant_id' => $tenant->id, 'marca' => 'Moura', 'modelo' => 'M100', 'amperagem_ah' => 100, 'numero_serie' => 'S'.uniqid()], $extra));
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

    public function test_installing_updates_the_state_the_odometer_and_allows_more_than_one_battery_per_vehicle(): void
    {
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $a = $this->bateria($tenant);
        $b = $this->bateria($tenant);

        $inst = $this->servico->instalar($a, $ativo, 10400, $admin);
        $this->servico->instalar($b, $ativo, 10400, $admin);

        $this->assertSame(FrotaBateria::MONTADA, $a->fresh()->situacao);
        $this->assertSame('bateria', $inst->componente_type);
        $this->assertNull($inst->posicao);
        $this->assertSame(10400.0, (float) $ativo->fresh()->odometro_atual);
        $this->assertSame('bateria', FrotaLeituraOdometro::withoutGlobalScopes()->where('ativo_id', $ativo->id)->orderBy('created_at')->first()->origem);
        $this->assertSame(2, $ativo->bateriasMontadas()->count());

        // a mesma bateria não pode estar aberta duas vezes (regra no serviço e no banco)
        $this->erroEm(fn () => $this->servico->instalar($a->fresh(), $ativo, 10500), 'bateria');
        $this->expectException(QueryException::class);
        FrotaInstalacaoComponente::create(['tenant_id' => $tenant->id, 'ativo_id' => $ativo->id, 'componente_type' => 'bateria', 'componente_id' => $a->id,
            'instalado_em' => now(), 'odometro_instalacao' => 1]);
    }

    public function test_installation_rules(): void
    {
        [$tenant] = $this->cliente();
        $maquina = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador', 'tag' => 'G-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL]);

        $this->erroEm(fn () => $this->servico->instalar($this->bateria($tenant), $maquina, 1), 'ativo');
        $this->erroEm(fn () => $this->servico->instalar($this->bateria($tenant, ['situacao' => FrotaBateria::SUCATEADA]), $this->veiculo($tenant), 10000), 'bateria');
        $this->erroEm(fn () => $this->servico->instalar($this->bateria($tenant), $this->veiculo($tenant), 5), 'odometro');   // menor que o atual
    }

    public function test_removal_destinations_and_validations(): void
    {
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $monta = fn (int $km = 10000) => tap($this->bateria($tenant), fn ($b) => $this->servico->instalar($b, $ativo, $km, $admin));

        $desgaste = $monta();
        $descarte = $monta();
        $garantia = $monta();
        $outro = $monta();

        $this->servico->remover($desgaste, 'desgaste', 12000, $admin);
        $this->servico->remover($descarte, 'descarte', 12000, $admin);
        $this->servico->remover($garantia, 'garantia', 12000, $admin);
        $this->servico->remover($outro, 'outro', 12000, $admin);

        $this->assertSame(FrotaBateria::SUCATEADA, $desgaste->fresh()->situacao);
        $this->assertSame(FrotaBateria::SUCATEADA, $descarte->fresh()->situacao);
        $this->assertSame(FrotaBateria::ESTOQUE, $garantia->fresh()->situacao);
        $this->assertSame(FrotaBateria::ESTOQUE, $outro->fresh()->situacao);
        $this->assertSame(0, $ativo->bateriasMontadas()->count());

        $this->erroEm(fn () => $this->servico->remover($garantia->fresh(), 'desgaste', 12000), 'bateria');   // não está instalada
        $nova = $monta(12000);
        $this->erroEm(fn () => $this->servico->remover($nova, 'inventado', 13000), 'motivo');
        $this->erroEm(fn () => $this->servico->remover($nova, 'desgaste', 5), 'odometro');
    }

    public function test_history_and_distance_follow_the_battery_across_vehicles(): void
    {
        [$tenant, $admin] = $this->cliente();
        $v1 = $this->veiculo($tenant);
        $v2 = $this->veiculo($tenant, ['placa' => 'QWE1R23', 'odometro_atual' => 50000]);
        $bateria = $this->bateria($tenant);

        $this->servico->instalar($bateria, $v1, 10000, $admin);
        $this->servico->remover($bateria, 'garantia', 14000, $admin);       // volta ao estoque
        $this->servico->instalar($bateria->fresh(), $v2, 50000, $admin);
        $v2->update(['odometro_atual' => 50800]);

        $bateria = FrotaBateria::find($bateria->id);
        $this->assertSame(2, $bateria->instalacoes()->count());
        $this->assertSame(4800, $bateria->kmRodado());                       // 4.000 + 800
    }

    public function test_alerts_for_age_warranty_and_voltage(): void
    {
        [$tenant] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $alertas = fn (FrotaBateria $b) => collect(FrotaBateria::find($b->id)->alertas())->keyBy('tipo');

        $nova = $this->bateria($tenant, ['comprada_em' => now()->subMonths(23)]);
        $this->assertFalse($alertas($nova)->has('idade'));
        $this->assertSame('atencao', $alertas($this->bateria($tenant, ['comprada_em' => now()->subMonths(24)]))['idade']['gravidade']);
        $this->assertSame('critica', $alertas($this->bateria($tenant, ['comprada_em' => now()->subMonths(36)]))['idade']['gravidade']);

        $this->assertTrue($alertas($this->bateria($tenant, ['garantia_ate' => now()->addDays(10)]))->has('garantia'));
        $this->assertTrue($alertas($this->bateria($tenant, ['garantia_ate' => now()]))->has('garantia'));
        $this->assertFalse($alertas($this->bateria($tenant, ['garantia_ate' => now()->addDays(40)]))->has('garantia'));
        $this->assertFalse($alertas($this->bateria($tenant, ['garantia_ate' => now()->subDays(2)]))->has('garantia'));

        $b = $this->bateria($tenant);
        $this->servico->registrarTeste($b, 12.6);
        $this->assertFalse($alertas($b)->has('tensao'));
        $this->servico->registrarTeste($b, 12.3);
        $this->assertSame('atencao', $alertas($b)['tensao']['gravidade']);
        $this->servico->registrarTeste($b, 11.9);
        $this->assertSame('critica', $alertas($b)['tensao']['gravidade']);

        $this->assertSame([], FrotaBateria::find($this->bateria($tenant, ['situacao' => FrotaBateria::SUCATEADA, 'comprada_em' => now()->subYears(5)])->id)->alertas());
        $this->erroEm(fn () => $this->servico->registrarTeste($b, 0), 'tensao');
        $this->erroEm(fn () => $this->servico->registrarTeste($b, 99), 'tensao');
    }

    public function test_the_voltage_in_a_full_checklist_is_assigned_to_the_battery_only_when_there_is_exactly_one(): void
    {
        [$tenant, $admin] = $this->cliente();
        ModelosChecklistPadrao::garantir($tenant->id);
        $completo = FrotaModeloChecklist::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('tipo', 'completo')->with('itens')->firstOrFail();
        $checklist = function (Asset $ativo, int $km) use ($completo, $admin) {
            return (new ChecklistFrotaService)->registrar($ativo, [
                'tipo' => FrotaChecklist::TIPO_SAIDA, 'modelo_id' => $completo->id, 'odometro' => $km,
                'respostas' => $completo->itens->mapWithKeys(fn ($i) => [$i->id => ['resultado' => 'ok', 'valor' => $i->descricao === 'Tensão da bateria' ? '12,4' : ($i->unidade ? '5' : null)]])->all(),
            ], $admin);
        };

        $semBateria = $this->veiculo($tenant);
        $checklist($semBateria, 10100);
        $this->assertNull(FrotaTesteBateria::where('ativo_id', $semBateria->id)->sole()->bateria_id);
        $this->assertSame(12.4, $semBateria->ultimaTensaoV());

        $umaBateria = $this->veiculo($tenant, ['placa' => 'XYZ9A87']);
        $b = $this->bateria($tenant);
        $this->servico->instalar($b, $umaBateria, 10000, $admin);
        $checklist($umaBateria, 10100);
        $this->assertSame($b->id, FrotaTesteBateria::where('ativo_id', $umaBateria->id)->sole()->bateria_id);

        $duasBaterias = $this->veiculo($tenant, ['placa' => 'QWE1R23']);
        $this->servico->instalar($this->bateria($tenant), $duasBaterias, 10000, $admin);
        $this->servico->instalar($this->bateria($tenant), $duasBaterias, 10000, $admin);
        $checklist($duasBaterias, 10100);
        $this->assertNull(FrotaTesteBateria::where('ativo_id', $duasBaterias->id)->sole()->bateria_id);
    }

    public function test_report_counts_batteries_per_vehicle_in_the_period_and_clients_stay_apart(): void
    {
        [$tenant, $admin] = $this->cliente();
        [$outro] = $this->cliente();
        $v1 = $this->veiculo($tenant);
        $v2 = $this->veiculo($tenant, ['placa' => 'QWE1R23']);
        $alheio = $this->veiculo($outro, ['placa' => 'ZZZ9Z99']);

        $instala = fn (Asset $v, $quando) => FrotaInstalacaoComponente::create([
            'tenant_id' => $v->tenant_id, 'ativo_id' => $v->id, 'componente_type' => 'bateria', 'componente_id' => (string) Str::uuid(),
            'instalado_em' => $quando, 'odometro_instalacao' => 1, 'removido_em' => now(), 'odometro_remocao' => 2,
        ]);
        $instala($v1, now()->subMonths(1));
        $instala($v1, now()->subMonths(5));
        $instala($v1, now()->subMonths(30));    // fora do período de 12 meses
        $instala($v2, now()->subMonths(2));
        $instala($alheio, now()->subMonths(1));

        $this->actingAs($admin);
        $pagina = Livewire::test(FrotaBateriasPorVeiculo::class)->assertSuccessful();
        $linhas = $pagina->instance()->consulta()->get()->keyBy('id');

        $this->assertSame(2, (int) $linhas[$v1->id]->baterias_no_periodo);
        $this->assertSame(1, (int) $linhas[$v2->id]->baterias_no_periodo);
        $this->assertFalse($linhas->has($alheio->id));

        $pagina->assertCanSeeTableRecords([$v1, $v2])->assertCanNotSeeTableRecords([$alheio]);
    }

    public function test_screens_work_for_the_admin_and_the_tab_only_shows_for_vehicles(): void
    {
        [$tenant, $admin] = $this->cliente();
        $ativo = $this->veiculo($tenant);
        $bateria = $this->bateria($tenant);
        $this->actingAs($admin);

        Livewire::test(ListFrotaBaterias::class)->assertSuccessful()->assertCanSeeTableRecords([$bateria])
            ->callTableAction('instalar', $bateria, ['ativo_id' => $ativo->id, 'odometro' => 10200]);
        $this->assertSame(FrotaBateria::MONTADA, $bateria->fresh()->situacao);

        Livewire::test(ListFrotaBaterias::class)->callTableAction('testar', $bateria->fresh(), ['tensao' => '12.5']);
        $this->assertSame(12.5, (float) $bateria->fresh()->ultimoTeste()->tensao);

        Livewire::test(ListFrotaBaterias::class)->callTableAction('remover', $bateria->fresh(), ['motivo' => 'desgaste', 'odometro' => 11000]);
        $this->assertSame(FrotaBateria::SUCATEADA, $bateria->fresh()->situacao);

        Livewire::test(BateriasRelationManager::class, ['ownerRecord' => $ativo, 'pageClass' => EditAsset::class])->assertSuccessful();
        $maquina = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador', 'tag' => 'G-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL]);
        $this->assertFalse(BateriasRelationManager::canViewForRecord($maquina, EditAsset::class));
        $this->assertTrue(BateriasRelationManager::canViewForRecord($ativo, EditAsset::class));
    }

    public function test_clients_do_not_see_each_others_batteries(): void
    {
        [$a] = $this->cliente();
        [$b, $adminB] = $this->cliente();
        $this->bateria($a);

        $this->actingAs($adminB);
        $this->assertSame(0, FrotaBateria::count());
    }
}
