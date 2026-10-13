<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\AssetExcelImporter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * A planilha do exercício de importação de ativos (Onda 1.1 do treinamento) precisa continuar valendo a cada versão do
 * sistema: 8 linhas boas e exatamente 2 erros propositais. Se o modelo de importação mudar, regenere com
 * scripts/treinamento/gerar-planilha-ativos.php.
 */
class TreinamentoPlanilhaAtivosTest extends TestCase
{
    use DatabaseTransactions;

    private const PLANILHA = __DIR__.'/../../docs/treinamento/onda-1/planilha-exercicio-ativos.xlsx';

    private function tenant(): Tenant
    {
        $plan = Plan::create(['name' => 'Plano Treino '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);

        return Tenant::create(['name' => 'Oravel', 'slug' => 'treino-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    public function test_planilha_do_exercicio_tem_8_linhas_boas_e_2_erros_propositais(): void
    {
        $this->assertFileExists(self::PLANILHA);

        $r = app(AssetExcelImporter::class)->import(self::PLANILHA, $this->tenant(), false, true);

        $this->assertSame(8, $r['created'], json_encode($r['errors'], JSON_UNESCAPED_UNICODE));
        $this->assertCount(2, $r['errors']);
        $mensagens = collect($r['errors'])->pluck('message')->implode(' | ');
        $this->assertStringContainsString('Nome/Modelo é obrigatório', $mensagens);
        $this->assertStringContainsString('repetido na planilha', $mensagens);
        $this->assertSame(['TRE-0005', 'TRE-0002'], collect($r['errors'])->pluck('patrimonio')->all());
    }

    public function test_validar_nao_grava_nada_e_importar_de_verdade_grava_so_as_8(): void
    {
        $t = $this->tenant();
        $importador = app(AssetExcelImporter::class);

        $importador->import(self::PLANILHA, $t, false, true);
        $this->assertSame(0, Asset::withoutGlobalScopes()->where('tenant_id', $t->id)->count());

        $r = $importador->import(self::PLANILHA, $t, false, false);
        $this->assertSame(8, $r['created']);
        $this->assertSame(8, Asset::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
    }
}
