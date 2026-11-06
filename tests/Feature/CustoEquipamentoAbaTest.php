<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\EditAsset;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustoManutencaoService;
use App\Services\Demo\LocadoraDemoService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** Aba "Custos e historico" no cadastro do equipamento (Inteligencia de Ativos), opcional por contrato. */
class CustoEquipamentoAbaTest extends TestCase
{
    use DatabaseTransactions;

    private function cenario(array $features): array
    {
        $plan = Plan::create([
            'name' => 'Plano Custo '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features,
        ]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'custo-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'adm-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Custoso', 'tag' => 'C-'.uniqid(), 'status' => 'disponivel',
            'acquisition_value' => 100000, 'acquisition_date' => now()->subYears(2), 'useful_life_years' => 10]);

        return [$tenant, $asset];
    }

    private function os(Tenant $t, Asset $a, string $tipo, float $mo, float $mat, float $log, Carbon $quando, bool $retrabalho = false): void
    {
        DB::table('maintenance_orders')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => $t->id, 'asset_id' => $a->id, 'os_number' => 'T-'.uniqid(), 'maintenance_type' => $tipo,
            'status' => 'Concluída', 'description' => 'OS de teste', 'labor_cost' => $mo, 'material_cost' => $mat, 'logistics_cost' => $log,
            'total_order_cost' => $mo + $mat + $log, 'is_rework' => $retrabalho, 'finished_at' => $quando, 'created_at' => $quando, 'updated_at' => $quando,
        ]);
    }

    public function test_resumo_e_mes_a_mes_somam_certo(): void
    {
        [$t, $a] = $this->cenario(['tabela_assets']);
        $this->os($t, $a, 'Preventiva', 100, 200, 0, now()->subMonths(2));
        $this->os($t, $a, 'Corretiva', 300, 500, 100, now()->subMonths(2));
        $this->os($t, $a, 'Emergência', 50, 50, 0, now()->subDays(3), true);
        $this->os($t, $a, 'Corretiva', 1000, 1000, 0, now()->subMonths(20)); // antes da janela de 18 meses

        $servico = app(CustoManutencaoService::class);
        $r = $servico->resumo($a);

        $this->assertSame(300.0 + 900.0 + 100.0 + 2000.0, $r['total']);
        $this->assertSame(4, $r['os_total']);
        $this->assertSame(1, $r['os_preventivas']);
        $this->assertSame(3, $r['os_corretivas']);
        $this->assertSame(1, $r['retrabalho']);
        $this->assertEqualsWithDelta(0.033, $r['razao'], 0.0001);
        $this->assertSame('ok', $r['situacao']['chave']);

        $meses = collect($servico->porMes($a, 18));
        $this->assertCount(18, $meses);
        $doMes = $meses->firstWhere('mes', now()->subMonths(2)->format('Y-m'));
        $this->assertSame(1200.0, $doMes['total']); // (100+200) + (300+500+100)
        $this->assertSame(2, $doMes['os']);
        // Acumulado inclui o custo anterior a janela (2.000) e fecha no total.
        $this->assertSame(2000.0 + 1200.0, $doMes['acumulado']);
        $this->assertSame($r['total'], $meses->last()['acumulado']);
    }

    public function test_sinalizacao_por_percentual_do_valor_de_aquisicao(): void
    {
        $this->assertSame('ok', CustoManutencaoService::situacao(0.39)['chave']);
        $this->assertSame('revisar', CustoManutencaoService::situacao(0.40)['chave']);
        $this->assertSame('revisar', CustoManutencaoService::situacao(0.59)['chave']);
        $this->assertSame('trocar_vender', CustoManutencaoService::situacao(0.60)['chave']);
        $this->assertSame('gray', CustoManutencaoService::situacao(null)['cor']);
    }

    public function test_aba_so_aparece_quando_o_contrato_libera_a_chave(): void
    {
        [$t, $a] = $this->cenario(['tabela_assets']);
        $this->os($t, $a, 'Corretiva', 70000, 0, 0, now()->subMonth());

        Livewire::test(EditAsset::class, ['record' => $a->getRouteKey()])->assertDontSee('Custos e histórico')->assertDontSee('Considerar trocar ou vender');

        $t->plan->update(['features' => ['tabela_assets', CustoManutencaoService::FEATURE]]);
        auth()->user()->unsetRelation('tenant'); // o usuario logado guarda o tenant (e o plano) em cache
        $this->assertTrue(CustoManutencaoService::habilitado());

        Livewire::test(EditAsset::class, ['record' => $a->getRouteKey()])
            ->assertSee('Custos e histórico')->assertSee('Considerar trocar ou vender')->assertSee('70%')->assertSee('R$ 70.000,00');
    }

    public function test_demo_tem_3_para_trocar_ou_vender_e_1_para_revisar(): void
    {
        app(LocadoraDemoService::class)->criar('x');
        $demo = app(LocadoraDemoService::class)->tenant();
        $this->actingAs(User::withoutGlobalScopes()->where('email', LocadoraDemoService::EMAIL_ADMIN)->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $servico = app(CustoManutencaoService::class);
        $situacoes = Asset::withoutGlobalScopes()->where('tenant_id', $demo->id)->get()
            ->mapWithKeys(fn ($a) => [$a->patrimonio => $servico->resumo($a)['situacao']['chave']]);

        $this->assertSame(['C-02', 'G-03', 'PT-02'], $situacoes->filter(fn ($v) => $v === 'trocar_vender')->keys()->sort()->values()->all());
        $this->assertSame(['PA-02'], $situacoes->filter(fn ($v) => $v === 'revisar')->keys()->all());
        $this->assertSame(21, $situacoes->filter(fn ($v) => $v === 'ok')->count());
    }

    public function test_linha_do_tempo_posiciona_locacoes_e_ordens_e_calcula_o_retorno(): void
    {
        [$t, $a] = $this->cenario(['tabela_assets', CustoManutencaoService::FEATURE]);
        DB::table('clients')->insert(['id' => $id = (string) Str::uuid(), 'tenant_id' => $t->id, 'name' => 'Cliente Linha', 'created_at' => now(), 'updated_at' => now()]);
        $contrato = fn (string $numero, $de, $ate, string $status, float $preco) => DB::table('contracts')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => $t->id, 'client_id' => $id, 'asset_id' => $a->id, 'contract_number' => $numero,
            'start_date' => $de, 'end_date' => $ate, 'status' => $status, 'is_active' => $status === 'Ativo', 'price' => $preco, 'local_tipo' => 'outro',
            'cidade_obra' => 'Campinas', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $contrato('LT-ANTIGO', now()->subMonths(30), now()->subMonths(25), 'Encerrado', 5000); // fora da janela de 18 meses
        $contrato('LT-ENCERRADO', now()->subMonths(10)->startOfDay(), now()->subMonths(6)->startOfDay(), 'Encerrado', 12000);
        $contrato('LT-ATIVO', now()->subMonths(2)->startOfDay(), now()->addMonths(3)->startOfDay(), 'Ativo', 9000);
        $this->os($t, $a, 'Preventiva', 100, 100, 0, now()->subMonths(8));
        $this->os($t, $a, 'Emergência', 1000, 2000, 500, now()->subDays(10), true);

        $linha = app(CustoManutencaoService::class)->linhaDoTempo($a, 18);

        $this->assertCount(2, $linha['contratos'], 'o contrato fora da janela nao aparece');
        $this->assertTrue(collect($linha['contratos'])->firstWhere('numero', 'LT-ATIVO')['ativo']);
        $this->assertFalse(collect($linha['contratos'])->firstWhere('numero', 'LT-ENCERRADO')['ativo']);
        foreach ($linha['contratos'] as $c) {
            $this->assertGreaterThanOrEqual(0, $c['esq']);
            $this->assertLessThanOrEqual(100.05, $c['esq'] + $c['larg']); // tolerancia de arredondamento
        }
        $this->assertCount(2, $linha['ordens']);
        $grande = collect($linha['ordens'])->firstWhere('tipo', 'Emergência');
        $pequena = collect($linha['ordens'])->firstWhere('tipo', 'Preventiva');
        $this->assertGreaterThan($pequena['tamanho'], $grande['tamanho'], 'ponto maior = OS mais cara');
        $this->assertTrue($grande['retrabalho']);
        $this->assertGreaterThan($pequena['esq'], $grande['esq'], 'OS mais recente fica mais a direita');
        $this->assertGreaterThan(0, $linha['taxa_locacao']);
        $this->assertLessThan(100, $linha['taxa_locacao']);
        $this->assertSame(26000.0, $linha['receita']);
        $this->assertSame(3700.0, $linha['custo']); // 200 + 3.500
        $this->assertSame(22300.0, $linha['saldo']);
        $this->assertCount(18, $linha['eixo']);

        Livewire::test(EditAsset::class, ['record' => $a->getRouteKey()])
            ->assertSee('Linha do tempo')->assertSee('Cliente Linha')->assertSee('Receita menos manutenção');
    }
}
