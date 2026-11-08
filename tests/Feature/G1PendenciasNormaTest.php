<?php

namespace Tests\Feature;

use App\Services\Catalogo\CatalogoInstalador;
use App\Services\Catalogo\PendenciasDeNorma;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * G1: o levantamento do que o responsavel tecnico precisa validar sai direto dos YAML (nada depende de lembrar). Garante que
 * nenhum item que cita norma ou intervalo provisorio fica de fora e que o checklist commitado nao fica velho.
 * SEGURANCA: so roda em banco com "test" no nome (nao toca dados, mas segue a regra do projeto).
 */
class G1PendenciasNormaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! str_contains((string) DB::connection()->getDatabaseName(), 'test')) {
            $this->markTestSkipped('Recusado: banco nao parece descartavel (nome sem "test").');
        }
    }

    /** Oraculo independente: conta direto nos YAML, sem passar pelo levantador. */
    private function contagemDireta(): int
    {
        $n = 0;

        foreach (glob(CatalogoInstalador::diretorio().'/*.yaml') as $f) {
            if (str_starts_with(basename($f), '_')) {
                continue;
            }

            $d = Yaml::parseFile($f);
            $n += ! empty($d['grupo']['validar']) ? 1 : 0;

            foreach (['requisitos', 'operadores', 'regras'] as $secao) {
                $n += count(array_filter($d[$secao] ?? [], fn ($l) => ! empty($l['validar_norma'])));
            }

            $n += count(array_filter($d['preventivas'] ?? [], fn ($p) => ! empty($p['parametros']['validar_manual'])));
        }

        return $n;
    }

    public function test_o_levantamento_bate_com_a_contagem_direta_dos_yaml(): void
    {
        $linhas = app(PendenciasDeNorma::class)->levantar();

        $this->assertGreaterThan(0, count($linhas));
        $this->assertSame($this->contagemDireta(), count($linhas));
    }

    public function test_todo_item_que_cita_norma_esta_no_levantamento(): void
    {
        $itens = collect(app(PendenciasDeNorma::class)->levantar());

        foreach (glob(CatalogoInstalador::diretorio().'/*.yaml') as $f) {
            if (str_starts_with(basename($f), '_')) {
                continue;
            }

            $d = Yaml::parseFile($f);

            foreach (array_merge($d['requisitos'] ?? [], $d['operadores'] ?? []) as $l) {
                if (! empty($l['norma'])) {
                    $this->assertTrue($itens->where('grupo', $d['grupo']['nome'])->where('norma', (string) $l['norma'])->isNotEmpty(),
                        "{$d['grupo']['slug']}: item com norma {$l['norma']} ficou fora do levantamento");
                }
            }
        }
    }

    public function test_gerador_tem_a_duvida_do_nr10_registrada_como_dado(): void
    {
        $g = collect(app(PendenciasDeNorma::class)->levantar())->where('grupo', 'Gerador')->where('tipo', 'Grupo');

        $this->assertCount(1, $g);
        $this->assertStringContainsString('NR-10', $g->first()['validar']);
    }

    public function test_intervalos_provisorios_de_munk_guindaste_e_veiculos_estao_listados(): void
    {
        $prev = collect(app(PendenciasDeNorma::class)->levantar())->filter(fn ($l) => str_starts_with($l['tipo'], 'Preventiva'));

        $this->assertEqualsCanonicalizing(['Guindaste', 'Munk', 'Munk', 'Caminhão', 'Reboque', 'Veículo leve'], $prev->pluck('grupo')->all());
    }

    public function test_o_checklist_commitado_esta_atualizado(): void
    {
        $arquivo = base_path('docs/G1_VALIDACAO_NORMAS.md');

        $this->assertFileExists($arquivo);
        $this->assertSame(app(PendenciasDeNorma::class)->markdown(), file_get_contents($arquivo), 'rode: php artisan catalogo:pendencias-norma --md');
    }

    public function test_comando_lista_e_alerta(): void
    {
        $this->artisan('catalogo:pendencias-norma')->expectsOutputToContain('a validar com o responsável técnico')->assertSuccessful();
    }
}
