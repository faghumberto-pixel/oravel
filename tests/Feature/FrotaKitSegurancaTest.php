<?php

namespace Tests\Feature;

use App\Filament\Resources\FrotaItemSegurancaResource\Pages\ListFrotaItensSeguranca;
use App\Models\Asset;
use App\Models\FrotaItemSeguranca;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\KitSegurancaService;
use App\Services\Frota\PendenciasFrotaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Gestão de Frota, Fase 12: kit de segurança (extintor, triângulo, macaco...), validade e conferência. */
class FrotaKitSegurancaTest extends TestCase
{
    use DatabaseTransactions;

    private KitSegurancaService $servico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servico = new KitSegurancaService;
    }

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_frota_itens_seguranca']]);
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

    private function erroEm(callable $fn, string $campo): void
    {
        try {
            $fn();
            $this->fail("Deveria recusar ({$campo}).");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($campo, $e->errors());
        }
    }

    public function test_criar_valida_nome_duplicidade_validade_e_so_veiculo(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);

        $this->erroEm(fn () => $this->servico->criar($v, ['nome' => ' ']), 'nome');
        $this->erroEm(fn () => $this->servico->criar($v, ['nome' => 'Extintor', 'tem_validade' => true]), 'validade');
        $this->erroEm(fn () => $this->servico->criar($this->veiculo($tenant)->forceFill(['grupo' => Asset::GRUPO_MAQUINA]), ['nome' => 'X']), 'ativo');

        $i = $this->servico->criar($v, ['nome' => 'Extintor', 'tem_validade' => true, 'validade' => now()->addYear()->toDateString()]);
        $this->erroEm(fn () => $this->servico->criar($v, ['nome' => 'extintor']), 'nome');
        $this->servico->desativar($i);
        $this->assertSame('extintor', $this->servico->criar($v, ['nome' => 'extintor'])->nome);
    }

    public function test_aplicar_padrao_cria_so_o_que_falta_e_o_extintor_nasce_sem_validade(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $this->servico->criar($v, ['nome' => 'Macaco']);

        $this->assertSame(count(KitSegurancaService::PADRAO) - 1, $this->servico->aplicarPadrao($v));
        $this->assertSame(0, $this->servico->aplicarPadrao($v));
        $extintor = FrotaItemSeguranca::where('ativo_id', $v->id)->where('nome', 'Extintor de incêndio')->sole();
        $this->assertTrue($extintor->tem_validade);
        $this->assertNull($extintor->validade);
    }

    public function test_alertas_ausente_obrigatorio_critica_e_nao_obrigatorio_atencao(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $triangulo = $this->servico->criar($v, ['nome' => 'Triângulo']);
        $colete = $this->servico->criar($v, ['nome' => 'Colete', 'obrigatorio' => false]);
        $this->servico->conferir($triangulo, ['presente' => false]);
        $this->servico->conferir($colete, ['presente' => false]);

        $this->assertSame([['gravidade' => 'critica', 'mensagem' => 'Triângulo ausente (obrigatório).']], KitSegurancaService::alertas($triangulo->fresh()));
        $this->assertSame('atencao', KitSegurancaService::alertas($colete->fresh())[0]['gravidade']);
    }

    public function test_validade_vencida_e_a_vencer_e_conferencia_antiga(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $vencido = $this->servico->criar($v, ['nome' => 'Extintor', 'tem_validade' => true, 'validade' => now()->subDays(5)->toDateString()]);
        $quase = $this->servico->criar($v, ['nome' => 'Primeiros socorros', 'tem_validade' => true, 'validade' => now()->addDays(10)->toDateString()]);
        $longe = $this->servico->criar($v, ['nome' => 'Macaco']);
        foreach ([$vencido, $quase, $longe] as $i) {
            $this->servico->conferir($i, ['presente' => true]);
        }

        $a = KitSegurancaService::alertas($vencido->fresh());
        $this->assertSame('critica', $a[0]['gravidade']);
        $this->assertStringContainsString('vencido há 5 dia(s)', $a[0]['mensagem']);
        $this->assertStringContainsString('vence em 10 dia(s)', KitSegurancaService::alertas($quase->fresh())[0]['mensagem']);
        $this->assertSame([], KitSegurancaService::alertas($longe->fresh()));

        $longe->update(['conferido_em' => now()->subDays(200)]);
        $this->assertStringContainsString('sem conferência', KitSegurancaService::alertas($longe->fresh())[0]['mensagem']);
        $nunca = $this->servico->criar($v, ['nome' => 'Cone']);
        $this->assertStringContainsString('sem conferência', KitSegurancaService::alertas($nunca)[0]['mensagem']);
    }

    public function test_conferir_atualiza_validade_data_e_recusa_futuro_e_validade_ausente(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $this->servico->aplicarPadrao($v);
        $ext = FrotaItemSeguranca::where('ativo_id', $v->id)->where('nome', 'Extintor de incêndio')->sole();

        $this->erroEm(fn () => $this->servico->conferir($ext, ['presente' => true]), 'validade');
        $this->erroEm(fn () => $this->servico->conferir($ext, ['presente' => true, 'validade' => now()->addYear()->toDateString(), 'conferido_em' => now()->addDay()->toDateString()]), 'conferido_em');

        $this->servico->conferir($ext, ['presente' => true, 'validade' => now()->addYear()->toDateString(), 'identificacao' => 'LAC-123']);
        $ext = $ext->fresh();
        $this->assertSame([], KitSegurancaService::alertas($ext));
        $this->assertSame('LAC-123', $ext->identificacao);
        $this->assertTrue($ext->conferido_em->isToday());

        $this->servico->desativar($ext);
        $this->erroEm(fn () => $this->servico->conferir($ext->fresh(), ['presente' => true]), 'item');
    }

    public function test_pendencias_do_kit_e_nao_geram_os(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $ext = $this->servico->criar($v, ['nome' => 'Extintor', 'tem_validade' => true, 'validade' => now()->subDay()->toDateString()]);
        $this->servico->conferir($ext, ['presente' => true]);
        $mac = $this->servico->criar($v, ['nome' => 'Macaco']);
        $this->servico->conferir($mac, ['presente' => false]);

        $p = (new PendenciasFrotaService)->doVeiculo($v)->where('categoria', 'kit');

        $this->assertSame(2, $p->count());
        $this->assertTrue($p->every(fn ($x) => $x['gravidade'] === 'critica'));
        $this->assertFalse((new PendenciasFrotaService)->permiteOs($p->first()));
    }

    public function test_um_cliente_nao_ve_item_do_outro_e_a_tela_funciona(): void
    {
        [$a, $adminA] = $this->cliente();
        [$b, $adminB] = $this->cliente();
        $vA = $this->veiculo($a);
        $this->servico->criar($vA, ['nome' => 'Macaco']);

        $this->actingAs($adminB);
        $this->assertSame(0, FrotaItemSeguranca::count());

        $this->actingAs($adminA);
        $this->assertSame(1, FrotaItemSeguranca::count());
        Livewire::test(ListFrotaItensSeguranca::class)
            ->callTableAction('aplicar_padrao', data: ['ativo_id' => $vA->id])
            ->callTableAction('novo_item', data: ['ativo_id' => $vA->id, 'nome' => 'Cone', 'obrigatorio' => false, 'tem_validade' => false]);
        $this->assertSame(count(KitSegurancaService::PADRAO) - 1 + 2, FrotaItemSeguranca::where('ativo_id', $vA->id)->count());

        $macaco = FrotaItemSeguranca::where('nome', 'Macaco')->sole();
        Livewire::test(ListFrotaItensSeguranca::class)->callTableAction('conferir', $macaco, data: ['presente' => false, 'conferido_em' => now()->toDateString()]);
        $this->assertFalse($macaco->fresh()->presente);
    }
}
