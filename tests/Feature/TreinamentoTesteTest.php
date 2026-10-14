<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\TreinamentoResultadoResource;
use App\Mail\TreinamentoResultadoMail;
use App\Models\Course;
use App\Models\TreinamentoResultado;
use App\Models\User;
use App\Support\TreinamentoTeste;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Teste publico do treinamento "Cadastros, Propostas e Contas a Receber":
 * correcao no servidor, gabarito fora do HTML, registro, e-mail e protecoes.
 */
class TreinamentoTesteTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('treinamento-teste:127.0.0.1');
    }

    /**
     * Abre a pagina e monta as respostas: as $certas primeiras perguntas certas,
     * as demais erradas (ou em branco, se $emBranco).
     *
     * @return array<int, int> n => posicao escolhida na tela
     */
    private function respostas(int $certas, bool $emBranco = false): array
    {
        $this->get(route('treinamento.teste'))->assertOk();

        $ordem = session('treinamento_teste.ordem');
        $respostas = [];

        foreach (TreinamentoTeste::perguntas() as $i => $pergunta) {
            $n = $pergunta['n'];

            if ($i < $certas) {
                $respostas[$n] = array_search($pergunta['correct'], $ordem[$n], true);
            } elseif (! $emBranco) {
                $errada = collect($ordem[$n])->search(fn ($original) => $original !== $pergunta['correct']);
                $respostas[$n] = $errada;
            }
        }

        return $respostas;
    }

    private function enviar(array $respostas, array $extra = [])
    {
        return $this->post(route('treinamento.enviar'), array_merge([
            'nome' => 'Maria da Silva',
            'funcao' => 'Vendedora',
            'r' => $respostas,
        ], $extra));
    }

    public function test_the_bank_has_15_questions_in_order(): void
    {
        $perguntas = TreinamentoTeste::perguntas();

        $this->assertCount(15, $perguntas);
        $this->assertSame(range(1, 15), array_column($perguntas, 'n'));
    }

    /** @dataProvider casosDeNota */
    public function test_server_side_grading(int $certas, bool $emBranco, float $nota, bool $aprovado): void
    {
        Mail::fake();
        $this->enviar($this->respostas($certas, $emBranco))->assertRedirect(route('treinamento.resultado'));

        $resultado = TreinamentoResultado::latest('created_at')->first();

        $this->assertSame($certas, $resultado->acertos);
        $this->assertSame(15, $resultado->total);
        $this->assertEquals($nota, (float) $resultado->nota);
        $this->assertSame($aprovado, $resultado->aprovado);
    }

    public static function casosDeNota(): array
    {
        return [
            '15 certas' => [15, false, 10.0, true],
            '11 certas' => [11, false, 7.3, true],
            '10 certas' => [10, false, 6.7, false],
            '9 certas' => [9, false, 6.0, false],
            'tudo em branco' => [0, true, 0.0, false],
        ];
    }

    public function test_the_answer_key_is_not_in_the_page(): void
    {
        $html = $this->get(route('treinamento.teste'))->assertOk()->getContent();

        $this->assertStringNotContainsString('correct', $html);
        foreach (TreinamentoTeste::perguntas() as $pergunta) {
            if ($pergunta['why'] !== '') {
                $this->assertStringNotContainsString($pergunta['why'], $html);
            }
        }
        // Os valores dos botoes sao so' posicoes na tela (0 a 3).
        preg_match_all('/<input type="radio" name="r\[\d+\]" value="([^"]*)"/', $html, $m);
        $this->assertCount(60, $m[1]);
        $this->assertEqualsCanonicalizing([0, 1, 2, 3], array_unique(array_map('intval', $m[1])));
    }

    public function test_alternatives_are_shuffled_on_each_display(): void
    {
        $this->get(route('treinamento.teste'));
        $primeira = session('treinamento_teste.ordem');
        $this->get(route('treinamento.teste'));

        $this->assertNotSame($primeira, session('treinamento_teste.ordem'));
    }

    public function test_result_is_saved_and_emailed_to_the_company(): void
    {
        Mail::fake();

        $this->enviar($this->respostas(12), ['funcao' => 'Comercial'])->assertRedirect(route('treinamento.resultado'));

        $this->assertDatabaseHas('treinamento_resultados', [
            'treinamento' => 'cadastros-propostas-receber', 'nome' => 'Maria da Silva', 'funcao' => 'Comercial',
            'acertos' => 12, 'total' => 15, 'aprovado' => true, 'ip' => '127.0.0.1',
        ]);

        Mail::assertSent(TreinamentoResultadoMail::class, function (TreinamentoResultadoMail $mail) {
            return $mail->hasTo('contato@oravel.com.br')
                && str_contains($mail->envelope()->subject, 'Teste Oravel: Maria da Silva (Comercial) nota 8,0')
                && count($mail->linhas) === 15;
        });
    }

    public function test_the_email_body_lists_name_role_grade_and_answers(): void
    {
        Mail::fake();
        $this->enviar($this->respostas(14));

        Mail::assertSent(TreinamentoResultadoMail::class, function (TreinamentoResultadoMail $mail) {
            $html = $mail->render();

            return str_contains($html, 'Maria da Silva') && str_contains($html, 'Vendedora')
                && str_contains($html, '9,3') && str_contains($html, 'Aprovado')
                && substr_count($html, 'Certa') === 14 && substr_count($html, 'Errada') === 1;
        });
    }

    public function test_an_email_failure_does_not_lose_the_result(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP fora do ar'));

        $this->enviar($this->respostas(15))->assertRedirect(route('treinamento.resultado'));

        $this->assertSame(1, TreinamentoResultado::where('nome', 'Maria da Silva')->count());
        $this->get(route('treinamento.resultado'))->assertOk()->assertSee('10,0');
    }

    public function test_result_page_shows_the_right_answer_and_explanation_for_each_error(): void
    {
        Mail::fake();
        $this->enviar($this->respostas(9));

        $html = $this->get(route('treinamento.resultado'))->assertOk()->assertSee('Precisa refazer', false)->getContent();

        $erradas = array_slice(TreinamentoTeste::perguntas(), 9);
        foreach ($erradas as $pergunta) {
            $this->assertStringContainsString(e($pergunta['options'][$pergunta['correct']]), $html);
            $this->assertStringContainsString(e($pergunta['why']), $html);
        }
    }

    public function test_name_and_role_are_required_and_limited_to_120(): void
    {
        Mail::fake();

        $this->enviar([], ['nome' => ''])->assertSessionHasErrors('nome');
        $this->enviar([], ['funcao' => ''])->assertSessionHasErrors('funcao');
        $this->enviar([], ['nome' => str_repeat('a', 121)])->assertSessionHasErrors('nome');
        $this->enviar([], ['funcao' => str_repeat('a', 121)])->assertSessionHasErrors('funcao');

        $this->assertSame(0, TreinamentoResultado::count());
    }

    public function test_submissions_per_ip_are_limited_to_five_per_hour(): void
    {
        Mail::fake();
        $respostas = $this->respostas(15);

        for ($i = 0; $i < 5; $i++) {
            $this->get(route('treinamento.teste'));
            $this->enviar($this->respostas(15))->assertRedirect(route('treinamento.resultado'));
        }

        $this->get(route('treinamento.teste'));
        $this->enviar($respostas)->assertSessionHasErrors('limite');

        $this->assertSame(5, TreinamentoResultado::count());
    }

    public function test_honeypot_discards_robot_submissions(): void
    {
        Mail::fake();

        $this->enviar($this->respostas(15), ['website' => 'http://spam.example'])->assertRedirect(route('treinamento.teste'));

        $this->assertSame(0, TreinamentoResultado::count());
        Mail::assertNothingSent();
    }

    public function test_submitting_without_opening_the_page_asks_to_reload(): void
    {
        Mail::fake();

        $this->enviar([1 => 0])->assertSessionHasErrors('sessao');

        $this->assertSame(0, TreinamentoResultado::count());
    }

    public function test_pdf_route_is_404_while_the_file_is_missing(): void
    {
        if (TreinamentoTeste::temPdf()) {
            $this->markTestSkipped('O PDF já foi colocado em public/treinamento.');
        }

        $this->get(route('treinamento.pdf'))->assertNotFound();
        $this->get(route('treinamento.teste'))->assertDontSee('Baixar o material');
    }

    public function test_imported_course_has_four_lessons_and_fifteen_questions(): void
    {
        Artisan::call('academy:import');

        $curso = Course::where('slug', 'cadastros-propostas-e-contas-a-receber')->firstOrFail();
        $aulas = $curso->lessons()->orderBy('position')->get();

        $this->assertSame(
            ['cadastros-cliente-ativo', 'qrcode-consulta-campo', 'proposta-comercial-ciclo', 'proposta-ao-contas-a-receber'],
            $aulas->map(fn ($aula) => basename($aula->page_url, '.html'))->all()
        );
        $this->assertSame(15, $aulas->sum(fn ($aula) => $aula->questions()->count()));
        $this->assertTrue($aulas->every(fn ($aula) => filled($aula->body)));
    }

    public function test_only_the_super_admin_sees_the_central_resource(): void
    {
        config(['oravel.super_admins' => ['super@oravel.test']]);

        $super = new User(['email' => 'super@oravel.test']);
        $comum = new User(['email' => 'comum@oravel.test']);

        $this->actingAs($comum);
        $this->assertFalse(TreinamentoResultadoResource::canViewAny());

        $this->actingAs($super);
        $this->assertTrue(TreinamentoResultadoResource::canViewAny());
        $this->assertFalse(TreinamentoResultadoResource::canCreate());
        $this->assertFalse(TreinamentoResultadoResource::canEdit(new TreinamentoResultado));
        $this->assertFalse(TreinamentoResultadoResource::canDelete(new TreinamentoResultado));
    }
}
