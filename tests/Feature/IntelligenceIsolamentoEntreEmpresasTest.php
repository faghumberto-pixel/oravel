<?php

namespace Tests\Feature;

use App\Domain\Intelligence\Models\IntelligenceImport;
use App\Domain\Intelligence\Models\IntelligenceIndicator;
use App\Domain\Intelligence\Models\IntelligenceSnapshot;
use App\Domain\Intelligence\Services\ImportadorExcel;
use App\Domain\Intelligence\Support\Catalogo;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * Isolamento entre empresas no Intelligence: a empresa A importa uma planilha e a empresa B (logada) não pode ver nada dela.
 * Rodar SÓ em banco descartável (usa DatabaseTransactions, como os demais testes do Intelligence).
 */
class IntelligenceIsolamentoEntreEmpresasTest extends TestCase
{
    use DatabaseTransactions;

    private function empresa(string $nome): array
    {
        $plano = Plan::create(['name' => 'P '.$nome.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $t = Tenant::create(['name' => $nome, 'slug' => str($nome)->slug().'-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $u = User::create(['name' => 'U '.$nome, 'email' => 'u-'.uniqid().'@teste.com', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);

        return [$t, $u];
    }

    private function planilha(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'iso').'.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        $w->getCurrentSheet()->setName(Catalogo::SHEET);
        $t = array_column(Catalogo::campos(), 'title');
        $dados = ['Data de Referência' => '30/09/2026', 'Porte' => 'Médio', 'Total de Equipamentos' => 100, 'Equipamentos Locados' => 70];
        $w->addRow(Row::fromValues(array_column(Catalogo::campos(), 'group')));
        $w->addRow(Row::fromValues($t));
        $w->addRow(Row::fromValues(array_map(fn ($x) => $dados[$x] ?? null, $t)));
        $w->close();

        return $path;
    }

    public function test_empresa_b_logada_nao_ve_importacao_snapshot_nem_indicadores_da_empresa_a(): void
    {
        [$a] = $this->empresa('Locadora A');
        [, $usuarioB] = $this->empresa('Locadora B');

        $r = app(ImportadorExcel::class)->importar($this->planilha(), $a);
        $this->assertTrue($r['ok'], implode(' | ', $r['erros']));

        // Pré-condição: os dados de A existem de verdade (senão o teste passaria por vazio).
        $this->assertSame(1, IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $a->id)->count());

        $this->actingAs($usuarioB);
        $this->assertSame(0, IntelligenceSnapshot::count());
        $this->assertSame(0, IntelligenceImport::count());
        $this->assertSame(0, IntelligenceIndicator::count());
    }

    public function test_empresa_a_logada_ve_os_proprios_dados(): void
    {
        [$a, $usuarioA] = $this->empresa('Locadora A');
        $this->empresa('Locadora B');

        app(ImportadorExcel::class)->importar($this->planilha(), $a);

        $this->actingAs($usuarioA);
        $this->assertSame(1, IntelligenceSnapshot::count());
        $this->assertSame(1, IntelligenceImport::count());
    }
}
