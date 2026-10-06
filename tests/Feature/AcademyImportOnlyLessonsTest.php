<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** academy:import --refresh-content --only-lessons=... atualiza só as aulas pedidas (06/10/2026). */
class AcademyImportOnlyLessonsTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/academy-test-'.uniqid();
        mkdir($this->dir.'/lessons', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/lessons/*') ?: [] as $f) {
            unlink($f);
        }
        foreach (glob($this->dir.'/*.json') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->dir.'/lessons');
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function write(string $a, string $b, string $summaryA = 'resumo A', array $questions = []): void
    {
        $slug = 'curso-teste-'.substr(md5($this->dir), 0, 6);
        file_put_contents($this->dir.'/courses.json', json_encode([[
            'title' => 'Curso Teste '.$slug,
            'lessons' => [
                ['title' => 'Aula A', 'href' => '/t/a-'.$slug.'.html', 'slug' => 'ta', 'summary' => $summaryA, 'feature' => null],
                ['title' => 'Aula B', 'href' => '/t/b-'.$slug.'.html', 'slug' => 'tb', 'summary' => 'resumo B', 'feature' => null],
            ],
        ]]));
        file_put_contents($this->dir.'/lessons/ta.html', $a);
        file_put_contents($this->dir.'/lessons/tb.html', $b);
        file_put_contents($this->dir.'/quiz.json', json_encode($questions ? [['page' => '/t/a-'.$slug.'.html', 'questions' => $questions]] : []));
    }

    private function body(string $letter): ?string
    {
        return Lesson::where('page_url', 'like', '%/t/'.$letter.'-%')->latest('id')->first()?->body;
    }

    public function test_refresh_with_only_lessons_updates_just_the_listed_lessons_and_adds_new_questions(): void
    {
        $this->write('<p>A v1</p>', '<p>B v1</p>');
        $this->artisan('academy:import', ['--dir' => $this->dir])->assertSuccessful();
        $this->assertSame('<p>A v1</p>', $this->body('a'));
        $this->assertSame('<p>B v1</p>', $this->body('b'));

        $this->write('<p>A v2</p>', '<p>B v2</p>', 'resumo A novo', [['q' => 'Pergunta nova?', 'options' => ['x', 'y'], 'correct' => 1, 'why' => 'porque']]);

        // Sem --refresh-content nada é sobrescrito, mas a pergunta nova entra.
        $this->artisan('academy:import', ['--dir' => $this->dir])->assertSuccessful();
        $this->assertSame('<p>A v1</p>', $this->body('a'));

        // Com --only-lessons=ta só a aula A é atualizada; a B continua como estava.
        $this->artisan('academy:import', ['--dir' => $this->dir, '--refresh-content' => true, '--only-lessons' => 'ta'])->assertSuccessful();
        $this->assertSame('<p>A v2</p>', $this->body('a'));
        $this->assertSame('<p>B v1</p>', $this->body('b'));
        $this->assertSame('resumo A novo', Lesson::where('page_url', 'like', '%/t/a-%')->latest('id')->first()->summary);

        $lessonA = Lesson::where('page_url', 'like', '%/t/a-%')->latest('id')->first();
        $this->assertSame(1, $lessonA->questions()->where('question', 'Pergunta nova?')->count());

        // Sem --only-lessons, --refresh-content continua atualizando tudo.
        $this->artisan('academy:import', ['--dir' => $this->dir, '--refresh-content' => true])->assertSuccessful();
        $this->assertSame('<p>B v2</p>', $this->body('b'));
        Course::where('title', 'like', 'Curso Teste%')->delete();
    }
}
