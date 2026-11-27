<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Popula Clientes, Orçamentos, Propostas Comerciais e Contratos da empresa de teste do Vendas: só adiciona e desfaz só o que fez. */
class DemoCompletarComercialTest extends TestCase
{
    use DatabaseTransactions;

    private const ARQUIVOS = ['demo-vendas-ids.json', 'demo-vendas-completar-ids.json', 'demo-comercial-ids.json'];

    private string $nome;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::ARQUIVOS as $a) {
            File::delete(storage_path('app/'.$a));
        }
        $this->nome = 'Completar Comercial '.uniqid();
    }

    protected function tearDown(): void
    {
        foreach (self::ARQUIVOS as $a) {
            File::delete(storage_path('app/'.$a));
        }
        parent::tearDown();
    }

    private function empresa(string $nome): Tenant
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_crm_leads' => true]]);

        return Tenant::create(['name' => $nome, 'slug' => 'cc-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function n(Tenant $t, string $tabela): int
    {
        return DB::table($tabela)->where('tenant_id', $t->id)->count();
    }

    private function preparada(): Tenant
    {
        $t = $this->empresa($this->nome);
        $this->assertSame(0, Artisan::call('demo:popular-vendas', ['--tenant' => $this->nome]));
        $this->assertSame(0, Artisan::call('demo:completar-vendas', ['--tenant' => $this->nome]));

        return $t;
    }

    public function test_popula_clientes_orcamentos_propostas_e_contratos_ligados_as_contas_do_crm(): void
    {
        $t = $this->preparada();

        $this->assertSame(0, Artisan::call('demo:completar-comercial', ['--tenant' => $this->nome]));

        $this->assertSame(8, $this->n($t, 'clients'));
        $this->assertSame(10, $this->n($t, 'quotes'));
        $this->assertSame(8, $this->n($t, 'proposta_comerciais'));
        $this->assertSame(6, $this->n($t, 'contracts'));
        $this->assertSame(0, DB::table('crm_contas')->where('tenant_id', $t->id)->whereNull('client_id')->count(), 'toda conta do CRM ganhou o cliente correspondente');
        $this->assertSame(6, DB::table('contracts')->where('tenant_id', $t->id)->distinct()->count('contract_number'), 'números de contrato distintos');
        $this->assertGreaterThanOrEqual(3, DB::table('quotes')->where('tenant_id', $t->id)->whereNotNull('approval_token')->count(), 'orçamentos enviados têm o link público');
    }

    public function test_a_soma_dos_itens_bate_com_o_total_de_cada_orcamento_e_proposta(): void
    {
        $t = $this->preparada();
        $this->assertSame(0, Artisan::call('demo:completar-comercial', ['--tenant' => $this->nome]));

        foreach (DB::table('quotes')->where('tenant_id', $t->id)->get() as $q) {
            $itens = DB::table('quote_items')->where('quote_id', $q->id)->get();
            $this->assertGreaterThanOrEqual(2, $itens->count());
            $this->assertEqualsWithDelta((float) $q->total_value, (float) $itens->sum('subtotal'), 0.001, 'orçamento: total = soma dos itens');
        }
        foreach (DB::table('proposta_comerciais')->where('tenant_id', $t->id)->get() as $p) {
            $itens = DB::table('proposta_comercial_itens')->where('proposta_comercial_id', $p->id)->get();
            $this->assertGreaterThanOrEqual(2, $itens->count());
            $this->assertEqualsWithDelta((float) $p->total_value, (float) $itens->sum('subtotal'), 0.001, 'proposta: total = soma dos itens');
        }
    }

    public function test_a_impressao_do_orcamento_e_do_contrato_funciona_com_esses_dados(): void
    {
        $t = $this->preparada();
        $this->assertSame(0, Artisan::call('demo:completar-comercial', ['--tenant' => $this->nome]));

        $quote = Quote::withoutGlobalScopes()->where('tenant_id', $t->id)->whereNotNull('approval_token')->firstOrFail()->load(['client', 'items']);
        $htmlQ = view('pdf.quote', ['quote' => $quote, 'generatedAt' => 'x'])->render();
        $this->assertStringContainsString(e($quote->client->name), $htmlQ);
        $this->assertStringContainsString(e($quote->items->first()->description), $htmlQ);

        $contrato = Contract::withoutGlobalScopes()->where('tenant_id', $t->id)->where('status', 'Ativo')->firstOrFail()->load(['client', 'asset']);
        $this->assertNull($contrato->asset, 'contrato de serviço, sem equipamento');
        $htmlC = view('pdf.contract', ['contract' => $contrato, 'generatedAt' => 'x'])->render();
        $this->assertStringContainsString($contrato->contract_number, $htmlC);
        $this->assertStringContainsString(e($contrato->client->name), $htmlC);
    }

    public function test_recusa_sem_contas_no_crm_nao_roda_duas_vezes_e_nao_mistura_com_dado_real(): void
    {
        $vazia = $this->empresa('Vazia '.uniqid());
        $this->assertSame(1, Artisan::call('demo:completar-comercial', ['--tenant' => $vazia->name]));

        $t = $this->preparada();
        $this->assertSame(0, Artisan::call('demo:completar-comercial', ['--tenant' => $this->nome]));
        $clientes = $this->n($t, 'clients');
        $this->assertSame(1, Artisan::call('demo:completar-comercial', ['--tenant' => $this->nome]), 'segunda vez recusa');
        $this->assertSame($clientes, $this->n($t, 'clients'));

        File::delete(storage_path('app/demo-comercial-ids.json'));
        $this->assertSame(1, Artisan::call('demo:completar-comercial', ['--tenant' => $this->nome]), 'empresa que já tem clientes recusa');
    }

    public function test_remover_desfaz_so_o_que_o_comando_fez_e_preserva_o_crm(): void
    {
        $t = $this->preparada();
        $contas = $this->n($t, 'crm_contas');
        $propostasCrm = $this->n($t, 'crm_propostas');
        $outra = $this->empresa('Outra '.uniqid());

        $this->assertSame(0, Artisan::call('demo:completar-comercial', ['--tenant' => $this->nome]));
        $this->assertSame(0, Artisan::call('demo:completar-comercial', ['--tenant' => $this->nome, '--remover' => true]));

        foreach (['clients', 'quotes', 'quote_items', 'proposta_comerciais', 'proposta_comercial_itens', 'contracts'] as $tabela) {
            $this->assertSame(0, $this->n($t, $tabela), $tabela);
        }
        $this->assertSame($contas, $this->n($t, 'crm_contas'), 'as contas do CRM continuam');
        $this->assertSame($propostasCrm, $this->n($t, 'crm_propostas'), 'as propostas do CRM continuam');
        $this->assertSame(0, DB::table('crm_contas')->where('tenant_id', $t->id)->whereNotNull('client_id')->count(), 'ligação conta => cliente desfeita');
        $this->assertSame(0, $this->n($outra, 'clients'), 'outra empresa nunca é tocada');
    }
}
