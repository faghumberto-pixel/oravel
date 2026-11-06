<?php

namespace Tests\Feature;

use App\Filament\Pages\VisaoGeralEstoque;
use App\Models\Plan;
use App\Models\StockCount;
use App\Models\StockPlacement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Demo\LocadoraDemoEstoque;
use App\Services\Demo\LocadoraDemoService;
use App\Services\Estoque\EstoqueUnificado;
use App\Services\Estoque\RastreioDoItem;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Estoque estruturado da Locadora Demo: grupos, enderecos, pecas/oleo, itens agregados, contagens com historico; idempotente e isolado. */
class LocadoraDemoEstoqueTest extends TestCase
{
    use DatabaseTransactions;

    private const TABELAS = ['stock_groups', 'stock_locations', 'stock_placements', 'stock_counts', 'stock_count_items', 'parts', 'warehouses', 'aggregate_items', 'aggregate_item_types'];

    private function contar(string $tenantId): array
    {
        return collect(self::TABELAS)->mapWithKeys(fn ($t) => [$t => DB::table($t)->where('tenant_id', $tenantId)->count()])->all();
    }

    public function test_demo_nasce_com_estoque_estruturado_idempotente_e_isolado(): void
    {
        $plano = Plan::create(['name' => 'Real '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $real = Tenant::create(['name' => 'Real '.uniqid(), 'slug' => 'real-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $antes = $this->contar((string) $real->id);

        $r = app(LocadoraDemoService::class)->criar('SenhaDemo123');
        $demo = Tenant::withoutGlobalScopes()->where('slug', LocadoraDemoService::SLUG)->firstOrFail();
        $c = $this->contar((string) $demo->id);

        $this->assertSame(6, $c['stock_groups']);
        $this->assertGreaterThanOrEqual(30, $c['stock_locations']);
        $this->assertGreaterThan(40, $c['stock_placements']);
        $this->assertSame(2, $c['stock_counts']);
        $this->assertSame(7, $c['parts']);
        $this->assertSame(5, $c['aggregate_item_types']);
        $this->assertSame(2, $r['estoque']['contagens']);
        $this->assertGreaterThan(0, $r['estoque']['itens_sem_endereco']);
        $this->assertTrue($demo->plan->hasFeature('tabela_stock_structure'));

        (new LocadoraDemoEstoque)->popular($demo);
        $this->assertSame($c, $this->contar((string) $demo->id));
        $this->assertSame($antes, $this->contar((string) $real->id));
    }

    public function test_contagens_da_demo_tem_historico_de_quem_contou_e_quando(): void
    {
        app(LocadoraDemoService::class)->criar('SenhaDemo123');

        $finalizada = StockCount::withoutGlobalScopes()->where('status', StockCount::FINALIZADA)->firstOrFail();
        $aberta = StockCount::withoutGlobalScopes()->where('status', StockCount::EM_CONTAGEM)->firstOrFail();
        $this->assertTrue($finalizada->finished_at->lt(now()->subDays(15)));
        $this->assertGreaterThan($finalizada->created_at, $finalizada->finished_at);

        foreach ([$finalizada, $aberta] as $contagem) {
            $entradas = DB::table('stock_count_entries')->whereIn('stock_count_item_id', DB::table('stock_count_items')->where('stock_count_id', $contagem->id)->select('id'))->get();
            $this->assertGreaterThan(10, $entradas->count());
            $this->assertGreaterThanOrEqual(2, $entradas->pluck('counted_by_name')->unique()->count(), 'mais de uma pessoa contou');
            $this->assertContains('celular', $entradas->pluck('source')->all());
            $this->assertContains('web', $entradas->pluck('source')->all());
        }

        $progresso = $aberta->items()->whereNotNull('counted_quantity')->count() / $aberta->items()->count();
        $this->assertGreaterThan(0.5, $progresso);
        $this->assertLessThan(1.0, $progresso);
        $this->assertGreaterThan(0, $aberta->items()->whereRaw('abs(counted_quantity - expected_quantity) >= 0.001')->count(), 'ha divergencias para conferir');
    }

    public function test_visao_geral_rastreio_e_filtros_funcionam_com_os_dados_da_demo(): void
    {
        app(LocadoraDemoService::class)->criar('SenhaDemo123');
        $admin = User::withoutGlobalScopes()->where('email', LocadoraDemoService::EMAIL_ADMIN)->firstOrFail();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $servico = app(EstoqueUnificado::class);
        $todos = $servico->todos();
        $this->assertEqualsCanonicalizing(['Material', 'Peça / óleo', 'Item agregado'], $todos->pluck('tipo_rotulo')->unique()->all());
        foreach (['Administração e escritório', 'Equipamentos', 'Veículos', 'Manutenção', 'Logística'] as $grupo) {
            $this->assertTrue($todos->contains('grupo', $grupo), $grupo);
        }
        $this->assertSame(0, $todos->where('grupo', 'Sem grupo')->count());
        $this->assertGreaterThan(0, $servico->itens(['endereco' => 'sem'])->count());
        $this->assertGreaterThan(0, $servico->itens(['situacao' => 'abaixo'])->count());
        $this->assertTrue($servico->itens(['corredor' => 'D'])->every(fn ($l) => in_array('D', $l['corredores'], true)));

        Livewire::test(VisaoGeralEstoque::class)->assertSee('Papel sulfite A4')
            ->set('busca', 'óleo de motor 15w40')->assertSee('Óleo de motor 15W40')->assertDontSee('Papel sulfite')
            ->call('limpar')->set('busca', 'cinta')->assertSee('Cinta de içamento');

        // oleo: movimentos (compras e saidas), uso na frota (troca de oleo) e contagens
        $oleo = $todos->firstWhere('nome', 'Óleo de motor 15W40 (litro)');
        $eventos = app(RastreioDoItem::class)->eventos(StockPlacement::PART, $oleo['id']);
        $tipos = $eventos->pluck('tipo')->unique()->all();
        foreach (['entrada', 'saida', 'uso'] as $tipo) {
            $this->assertContains($tipo, $tipos);
        }
        $this->assertTrue($eventos->contains(fn ($e) => str_contains($e['titulo'], 'troca de óleo')));
    }
}
