<?php

namespace Tests\Feature;

use App\Models\CrmProposta;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Completa a empresa de teste do Vendas (catálogo, contas, itens das propostas, veículos e despesas): só adiciona e desfaz só o que fez. */
class DemoCompletarVendasTest extends TestCase
{
    use DatabaseTransactions;

    private string $nome;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['demo-vendas-ids.json', 'demo-vendas-completar-ids.json'] as $a) {
            File::delete(storage_path('app/'.$a));
        }
        $this->nome = 'Completar Vendas '.uniqid();
    }

    protected function tearDown(): void
    {
        foreach (['demo-vendas-ids.json', 'demo-vendas-completar-ids.json'] as $a) {
            File::delete(storage_path('app/'.$a));
        }
        parent::tearDown();
    }

    private function empresa(string $nome): Tenant
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_crm_leads' => true]]);

        return Tenant::create(['name' => $nome, 'slug' => 'cv-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function n(Tenant $t, string $tabela): int
    {
        return DB::table($tabela)->where('tenant_id', $t->id)->count();
    }

    private function preparada(): Tenant
    {
        $t = $this->empresa($this->nome);
        $this->assertSame(0, Artisan::call('demo:popular-vendas', ['--tenant' => $this->nome]));

        return $t;
    }

    public function test_completa_catalogo_contas_veiculos_e_despesas_e_liga_tudo(): void
    {
        $t = $this->preparada();
        $leads = $this->n($t, 'crm_leads');

        $this->assertSame(0, Artisan::call('demo:completar-vendas', ['--tenant' => $this->nome]));

        $this->assertSame(10, $this->n($t, 'crm_produtos'));
        $this->assertSame(8, $this->n($t, 'crm_contas'));
        $this->assertSame(8, $this->n($t, 'crm_conta_contatos'));
        $this->assertSame(3, $this->n($t, 'crm_veiculos'));
        $this->assertGreaterThan(10, $this->n($t, 'crm_visita_despesas'));
        $this->assertSame($leads, $this->n($t, 'crm_leads'), 'nenhum lead novo');
        foreach (['crm_leads', 'crm_propostas', 'crm_visitas'] as $tabela) {
            $this->assertSame(0, DB::table($tabela)->where('tenant_id', $t->id)->whereNull('crm_conta_id')->count(), "$tabela ligada a uma conta");
        }
    }

    public function test_cada_proposta_ganha_itens_e_a_soma_bate_com_o_total_que_ela_ja_tinha(): void
    {
        $t = $this->preparada();
        $antes = DB::table('crm_propostas')->where('tenant_id', $t->id)->pluck('total', 'id')->map(fn ($v) => round((float) $v, 2))->all();

        $this->assertSame(0, Artisan::call('demo:completar-vendas', ['--tenant' => $this->nome]));

        foreach ($antes as $id => $total) {
            $itens = DB::table('crm_proposta_itens')->where('crm_proposta_id', $id)->get();
            $this->assertGreaterThanOrEqual(1, $itens->count(), 'proposta sem item');
            $desconto = round((float) DB::table('crm_propostas')->where('id', $id)->value('desconto'), 2);
            $this->assertEqualsWithDelta($total, round((float) $itens->sum('subtotal') - $desconto, 2), 0.001, 'soma dos itens - desconto = total da proposta');
            $this->assertLessThan(1000, $desconto, 'o desconto é só a pequena diferença, nunca metade da proposta');
            $this->assertGreaterThanOrEqual(0, $desconto);
            $this->assertLessThanOrEqual(240.0 + 0.001, $desconto, 'sobra menor que o item mais barato do catálogo');
            foreach ($itens as $item) {
                $this->assertContains((float) $item->preco_unitario, [7800.0, 5400.0, 6200.0, 4300.0, 1650.0, 950.0, 4800.0, 780.0, 240.0, 1180.0], 'preço de item sempre do catálogo');
            }
            $this->assertEqualsWithDelta($total, round((float) DB::table('crm_propostas')->where('id', $id)->value('total'), 2), 0.001, 'o total da proposta não mudou');
        }
        $primeira = CrmProposta::withoutGlobalScopes()->findOrFail(array_key_first($antes));
        $this->assertStringStartsWith('%PDF', $primeira->gerarPdf(), 'a proposta com itens imprime');
    }

    public function test_recusa_empresa_sem_propostas_nao_roda_duas_vezes_e_nao_mistura_com_dado_real(): void
    {
        $vazia = $this->empresa('Vazia '.uniqid());
        $this->assertSame(1, Artisan::call('demo:completar-vendas', ['--tenant' => $vazia->name]));

        $t = $this->preparada();
        $this->assertSame(0, Artisan::call('demo:completar-vendas', ['--tenant' => $this->nome]));
        $contas = $this->n($t, 'crm_contas');
        $this->assertSame(1, Artisan::call('demo:completar-vendas', ['--tenant' => $this->nome]), 'segunda vez recusa');
        $this->assertSame($contas, $this->n($t, 'crm_contas'));

        File::delete(storage_path('app/demo-vendas-completar-ids.json'));
        $this->assertSame(1, Artisan::call('demo:completar-vendas', ['--tenant' => $this->nome]), 'empresa que já tem catálogo/contas recusa');
    }

    public function test_remover_desfaz_so_o_que_o_comando_fez_e_preserva_o_resto(): void
    {
        $t = $this->preparada();
        $leads = $this->n($t, 'crm_leads');
        $propostas = $this->n($t, 'crm_propostas');
        $outra = $this->empresa('Outra '.uniqid());

        $this->assertSame(0, Artisan::call('demo:completar-vendas', ['--tenant' => $this->nome]));
        $this->assertSame(0, Artisan::call('demo:completar-vendas', ['--tenant' => $this->nome, '--remover' => true]));

        foreach (['crm_produtos', 'crm_contas', 'crm_conta_contatos', 'crm_veiculos', 'crm_visita_despesas'] as $tabela) {
            $this->assertSame(0, $this->n($t, $tabela), $tabela);
        }
        $this->assertSame(0, DB::table('crm_proposta_itens')->whereIn('crm_proposta_id', DB::table('crm_propostas')->where('tenant_id', $t->id)->pluck('id'))->count());
        $this->assertSame($leads, $this->n($t, 'crm_leads'), 'os leads continuam');
        $this->assertSame($propostas, $this->n($t, 'crm_propostas'), 'as propostas continuam');
        $this->assertSame(0, DB::table('crm_propostas')->where('tenant_id', $t->id)->where('desconto', '!=', 0)->count(), 'o desconto original (zero) volta');
        $this->assertSame(0, DB::table('crm_leads')->where('tenant_id', $t->id)->whereNotNull('crm_conta_id')->count(), 'ligações desfeitas');
        $this->assertSame(0, $this->n($outra, 'crm_contas'), 'outra empresa nunca é tocada');
    }
}
