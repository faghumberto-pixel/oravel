<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\LessonQuestion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * academy:import so adicionava perguntas novas: ao reescrever uma aula, a
 * pergunta antiga (errada) ficava ao lado da nova. --refresh-quiz (com
 * --only-lessons) reaplica a prova das aulas escolhidas.
 */
class AcademyQuizRefreshTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('framework/testing/academy-'.Str::random(8));
        mkdir($this->dir, 0777, true);

        file_put_contents($this->dir.'/courses.json', json_encode([[
            'title' => 'Curso Refresh '.Str::random(6), 'description' => 'x',
            'lessons' => [
                ['title' => 'Aula A', 'href' => '/t/a.html', 'slug' => 'aula-a', 'summary' => 's', 'feature' => null],
                ['title' => 'Aula B', 'href' => '/t/b.html', 'slug' => 'aula-b', 'summary' => 's', 'feature' => null],
            ],
        ]]));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*'));
        rmdir($this->dir);

        parent::tearDown();
    }

    private function quiz(array $a, array $b = []): void
    {
        file_put_contents($this->dir.'/quiz.json', json_encode([
            ['page' => '/t/a.html', 'questions' => $a],
            ['page' => '/t/b.html', 'questions' => $b],
        ]));
    }

    private function q(string $texto, array $opcoes = ['x', 'y'], int $certa = 0): array
    {
        return ['q' => $texto, 'options' => $opcoes, 'correct' => $certa, 'why' => 'porque'];
    }

    private function importar(array $extra = []): void
    {
        Artisan::call('academy:import', array_merge(['--dir' => $this->dir, '--base' => 'https://t.test'], $extra));
    }

    private function aula(string $href): Lesson
    {
        return Lesson::where('page_url', 'https://t.test'.$href)->firstOrFail();
    }

    public function test_default_import_only_adds_new_questions_and_keeps_old_ones(): void
    {
        $this->quiz([$this->q('Velha')]);
        $this->importar();

        $this->quiz([$this->q('Nova')]);
        $this->importar();

        $this->assertEqualsCanonicalizing(['Velha', 'Nova'], $this->aula('/t/a.html')->questions()->pluck('question')->all());
    }

    public function test_refresh_quiz_replaces_the_questions_of_the_chosen_lesson_only(): void
    {
        $this->quiz([$this->q('Velha'), $this->q('Fica', ['a', 'b'], 0)], [$this->q('B velha')]);
        $this->importar();

        $this->quiz([$this->q('Fica', ['a', 'b', 'c'], 2), $this->q('Nova')], [$this->q('B nova')]);
        $this->importar(['--refresh-content' => true, '--only-lessons' => 'aula-a', '--refresh-quiz' => true]);

        $a = $this->aula('/t/a.html')->questions()->orderBy('position')->get();
        $this->assertSame(['Fica', 'Nova'], $a->pluck('question')->all());
        $this->assertSame(2, $a->first()->correct_index);
        $this->assertCount(3, $a->first()->options);
        $this->assertSame([1, 2], $a->pluck('position')->all());

        // A outra aula nao foi escolhida: continua so' aditiva.
        $this->assertEqualsCanonicalizing(['B velha', 'B nova'], $this->aula('/t/b.html')->questions()->pluck('question')->all());
    }

    public function test_refresh_quiz_keeps_old_questions_when_someone_already_delivered_the_exam(): void
    {
        $this->quiz([$this->q('Velha', ['a', 'b'], 0)]);
        $this->importar();

        $aula = $this->aula('/t/a.html');
        DB::table('lesson_quiz_submissions')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => (string) Str::uuid(), 'user_id' => (string) Str::uuid(),
            'lesson_id' => $aula->id, 'total_questions' => 1, 'correct_answers' => 1, 'delivered_at' => now(),
        ]);

        $this->quiz([$this->q('Velha', ['a', 'b', 'c'], 1), $this->q('Nova')]);
        $this->importar(['--refresh-content' => true, '--only-lessons' => 'aula-a', '--refresh-quiz' => true]);

        $perguntas = $aula->questions()->get();
        $this->assertEqualsCanonicalizing(['Velha', 'Nova'], $perguntas->pluck('question')->all());
        $velha = $perguntas->firstWhere('question', 'Velha');
        $this->assertSame(1, $velha->correct_index);
        $this->assertCount(3, $velha->options);
    }

    public function test_obsolete_question_is_kept_when_the_exam_was_delivered(): void
    {
        $this->quiz([$this->q('Sai'), $this->q('Fica')]);
        $this->importar();

        $aula = $this->aula('/t/a.html');
        DB::table('lesson_quiz_submissions')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => (string) Str::uuid(), 'user_id' => (string) Str::uuid(),
            'lesson_id' => $aula->id, 'total_questions' => 2, 'correct_answers' => 2, 'delivered_at' => now(),
        ]);

        $this->quiz([$this->q('Fica')]);
        $this->importar(['--refresh-content' => true, '--only-lessons' => 'aula-a', '--refresh-quiz' => true]);

        $this->assertSame(2, LessonQuestion::where('lesson_id', $aula->id)->count());
    }

    public function test_refresh_quiz_requires_only_lessons(): void
    {
        $this->quiz([$this->q('Velha')]);

        $codigo = Artisan::call('academy:import', ['--dir' => $this->dir, '--base' => 'https://t.test', '--refresh-quiz' => true]);

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('--only-lessons', Artisan::output());
    }
}
