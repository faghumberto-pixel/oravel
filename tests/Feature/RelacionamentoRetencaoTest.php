<?php

namespace Tests\Feature;

use App\Relacionamento\Models\RelContato;
use App\Relacionamento\Models\RelInteracao;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Relacionamento Oravel: prazo de guarda (LGPD) cumprido por relacionamento:purgar. DatabaseTransactions (ver Fase1Test). */
class RelacionamentoRetencaoTest extends TestCase
{
    use DatabaseTransactions;

    private function contato(string $email, int $mesesParado): RelContato
    {
        $c = RelContato::create(['email' => $email]);
        $c->forceFill(['updated_at' => now()->subMonths($mesesParado)])->saveQuietly();

        return $c;
    }

    private function inter(string $tipo, int $mesesAtras, ?RelContato $c = null): RelInteracao
    {
        return RelInteracao::create(['tipo' => $tipo, 'contato_id' => $c?->id, 'ocorrido_em' => now()->subMonths($mesesAtras)]);
    }

    public function test_apaga_uso_do_site_antigo_e_mantem_o_recente(): void
    {
        $velha = $this->inter('ferramenta', 25);
        $limite = $this->inter('artigo', 23);
        $recente = $this->inter('video', 1);

        $this->artisan('relacionamento:purgar')->assertSuccessful();

        $this->assertNull(RelInteracao::find($velha->id));
        $this->assertNotNull(RelInteracao::find($limite->id));
        $this->assertNotNull(RelInteracao::find($recente->id));
    }

    public function test_nunca_apaga_contato_comercial_da_equipe(): void
    {
        $comercial = $this->inter('contato_comercial', 60);

        $this->artisan('relacionamento:purgar')->assertSuccessful();

        $this->assertNotNull(RelInteracao::find($comercial->id));
    }

    public function test_apaga_contato_parado_sem_interacoes_e_mantem_o_que_ainda_tem_atividade(): void
    {
        $sumido = $this->contato('sumido@x.com', 30);
        $this->inter('artigo', 30, $sumido); // some junto com o contato
        $ativo = $this->contato('ativo@x.com', 30);
        $this->inter('artigo', 2, $ativo);    // interacao recente segura o contato
        $novo = $this->contato('novo@x.com', 1);

        $this->artisan('relacionamento:purgar')->assertSuccessful();

        $this->assertNull(RelContato::find($sumido->id));
        $this->assertNotNull(RelContato::find($ativo->id));
        $this->assertNotNull(RelContato::find($novo->id));
    }

    public function test_dry_run_nao_apaga_e_prazo_invalido_falha(): void
    {
        $velha = $this->inter('ferramenta', 40);

        $this->artisan('relacionamento:purgar --dry-run')->assertSuccessful();
        $this->assertNotNull(RelInteracao::find($velha->id));

        $this->artisan('relacionamento:purgar --meses=-3')->assertFailed();
        $this->assertNotNull(RelInteracao::find($velha->id));
    }

    public function test_respeita_o_prazo_informado(): void
    {
        $i = $this->inter('ferramenta', 8);

        $this->artisan('relacionamento:purgar --meses=6')->assertSuccessful();

        $this->assertNull(RelInteracao::find($i->id));
    }

    public function test_esta_agendado_diariamente(): void
    {
        $evento = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'relacionamento:purgar'));

        $this->assertNotNull($evento);
        $this->assertSame('30 3 * * *', $evento->expression);
    }

    public function test_visitas_aos_sites_tambem_expiram_por_agendamento(): void
    {
        $evento = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'web-analytics:prune'));

        $this->assertNotNull($evento);
        $this->assertSame('40 3 * * *', $evento->expression);
    }
}
