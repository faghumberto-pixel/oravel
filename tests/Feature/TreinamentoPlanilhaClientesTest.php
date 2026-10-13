<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\ClientExcelImporter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * A planilha do exercício de importação de clientes (Onda 1.2 do treinamento) precisa continuar valendo a cada versão do
 * sistema: 8 linhas boas e exatamente 2 erros propositais. Se o modelo de importação mudar, regenere com
 * scripts/treinamento/gerar-planilha-clientes.php.
 */
class TreinamentoPlanilhaClientesTest extends TestCase
{
    use DatabaseTransactions;

    private const PLANILHA = __DIR__.'/../../docs/treinamento/onda-1/planilha-exercicio-clientes.xlsx';

    private function tenant(): Tenant
    {
        $plan = Plan::create(['name' => 'Plano Treino '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);

        return Tenant::create(['name' => 'Oravel', 'slug' => 'treino-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    public function test_planilha_do_exercicio_tem_8_linhas_boas_e_2_erros_propositais(): void
    {
        $this->assertFileExists(self::PLANILHA);

        $r = app(ClientExcelImporter::class)->import(self::PLANILHA, $this->tenant(), false, true);

        $this->assertSame(8, $r['created'], json_encode($r['errors'], JSON_UNESCAPED_UNICODE));
        $this->assertCount(2, $r['errors']);
        $mensagens = collect($r['errors'])->pluck('message')->implode(' | ');
        $this->assertStringContainsString('Razão Social é obrigatória', $mensagens);
        $this->assertStringContainsString('repetido na planilha (primeira vez na linha 3)', $mensagens);
        $this->assertSame([7, 9], collect($r['errors'])->pluck('line')->all());
    }

    public function test_validar_nao_grava_nada_e_importar_de_verdade_grava_so_os_8(): void
    {
        $t = $this->tenant();
        $importador = app(ClientExcelImporter::class);

        $importador->import(self::PLANILHA, $t, false, true);
        $this->assertSame(0, Client::withoutGlobalScopes()->where('tenant_id', $t->id)->count());

        $r = $importador->import(self::PLANILHA, $t, false, false);
        $this->assertSame(8, $r['created']);
        $this->assertSame(8, Client::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
    }
}
