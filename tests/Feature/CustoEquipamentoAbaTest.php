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
}
