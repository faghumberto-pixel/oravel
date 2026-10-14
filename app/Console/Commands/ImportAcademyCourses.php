<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonQuestion;
use App\Models\LessonQuizSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Importa a Academia a partir dos arquivos versionados em database/data/academy/ (gerados por
 * `academy:build-content`): courses.json, lessons/{slug}.html e quiz.json. Nao depende de site
 * nem de pasta externa -- roda igual em DEV e em PRODUCAO.
 *
 * Idempotente e conservador: cursos/aulas sao identificados por slug/pagina e NUNCA tem texto
 * escrito na Central sobrescrito (descricao, resumo, corpo, video, anexo, ordem, "publicado"
 * so' sao gravados quando estao vazios), exceto com --refresh-content, que reaplica resumo e
 * corpo vindos dos arquivos. Aulas de assunto interno (SKIP) sao removidas se existirem.
 */
class ImportAcademyCourses extends Command
{
    protected $signature = 'academy:import
        {--dir= : Pasta dos arquivos (padrão: database/data/academy)}
        {--base=https://academy.oravel.com.br : Prefixo gravado em page_url (identificador da aula)}
        {--publish : Publica os cursos NOVOS (padrão: rascunho)}
        {--publish-only= : Títulos (separados por vírgula) dos cursos a PUBLICAR, novos ou já existentes}
        {--refresh-content : Reaplica resumo e corpo das aulas a partir dos arquivos (sobrescreve)}
        {--only-lessons= : Com --refresh-content, só as aulas com estes slugs (separados por vírgula), ex.: modulos__ativos}
        {--refresh-quiz : Com --only-lessons, reaplica as perguntas dessas aulas a partir do quiz.json: atualiza as de mesmo texto, adiciona as novas e remove as que saíram (só se ninguém entregou a prova da aula)}
        {--no-quiz : Não importa as perguntas (quiz.json)}
        {--dry-run : Só mostra o que seria importado}';

    /** Aulas de assunto interno que nao podem existir na Academia dos clientes. */
    private const REMOVED_PAGES = ['/multi-tenant.html'];

    public function handle(): int
    {
        $dir = rtrim((string) ($this->option('dir') ?: database_path('data/academy')), '/');
        if (! is_file($dir.'/courses.json')) {
            $this->error("courses.json não encontrado em {$dir}");

            return self::FAILURE;
        }

        $courses = json_decode((string) file_get_contents($dir.'/courses.json'), true) ?: [];
        $base = rtrim((string) $this->option('base'), '/');
        $dry = (bool) $this->option('dry-run');
        $refresh = (bool) $this->option('refresh-content');
        $onlyLessons = array_filter(array_map('trim', explode(',', (string) $this->option('only-lessons'))));
        $publishOnly = array_filter(array_map('trim', explode(',', (string) $this->option('publish-only'))));

        if (! $dry) {
            $removed = Lesson::whereIn('page_url', array_map(fn ($h) => $base.$h, self::REMOVED_PAGES))->delete();
            if ($removed) {
                $this->line("— removida(s) {$removed} aula(s) de assunto interno");
            }
        }

        foreach ($courses as $i => $c) {
            $this->info("{$c['title']} (".count($c['lessons']).' aulas)');
            if ($dry) {
                continue;
            }

            $course = Course::firstOrCreate(
                ['slug' => Str::slug($c['title'])],
                ['title' => $c['title'], 'position' => $i + 1, 'is_published' => (bool) $this->option('publish')],
            );
            if (! empty($c['description']) && (blank($course->description) || ($refresh && ! $onlyLessons))) {
                $course->description = $c['description'];
            }
            if (in_array($c['title'], $publishOnly, true)) {
                $course->is_published = true;
            }
            $course->save();

            foreach ($c['lessons'] as $j => $l) {
                $lesson = Lesson::firstOrNew(['course_id' => $course->id, 'page_url' => $base.$l['href']]);
                $lesson->title = $l['title'];
                if (! $lesson->exists) {
                    $lesson->position = $j + 1;
                }
                $lesson->feature_key ??= $l['feature'] ?? null;

                $file = "{$dir}/lessons/{$l['slug']}.html";
                // --only-lessons limita o --refresh-content às aulas listadas (as demais não são sobrescritas).
                $refreshThis = $refresh && (! $onlyLessons || in_array($l['slug'], $onlyLessons, true));
                if ($refreshThis || blank($lesson->summary)) {
                    $lesson->summary = $l['summary'] ?? $lesson->summary;
                }
                if (($refreshThis || blank($lesson->body)) && is_file($file)) {
                    $lesson->body = (string) file_get_contents($file) ?: null;
                }
                $lesson->save();
            }
        }

        if (! $dry && ! $this->option('no-quiz') && is_file($dir.'/quiz.json')) {
            $refreshPages = [];

            if ($this->option('refresh-quiz')) {
                if (! $onlyLessons) {
                    $this->error('--refresh-quiz exige --only-lessons (para não reescrever as provas de todas as aulas).');

                    return self::FAILURE;
                }

                foreach ($courses as $c) {
                    foreach ($c['lessons'] as $l) {
                        if (in_array($l['slug'], $onlyLessons, true)) {
                            $refreshPages[] = $l['href'];
                        }
                    }
                }
            }

            $this->importQuiz($dir.'/quiz.json', $base, $refreshPages);
        }

        $this->newLine();
        $this->info($dry ? 'Simulação concluída (nada gravado).' : 'Importação concluída.');

        return self::SUCCESS;
    }

    /**
     * quiz.json: [{"page": "/modulos/ativos.html", "questions": [{"q","options":[..],"correct":0,"why"}]}].
     * Cria so' as perguntas que ainda nao existem (mesmo texto na mesma aula): nao duplica e
     * nao mexe em pergunta editada/criada na Central.
     */
    /**
     * @param  array<int, string>  $refreshPages  páginas (href) cujas perguntas são reaplicadas por inteiro
     */
    private function importQuiz(string $file, string $base, array $refreshPages = []): void
    {
        $created = 0;
        $updated = 0;
        $removed = 0;
        $missing = [];

        foreach (json_decode((string) file_get_contents($file), true) ?: [] as $entry) {
            $lesson = Lesson::where('page_url', $base.$entry['page'])->first();
            if (! $lesson) {
                $missing[] = $entry['page'];

                continue;
            }

            if (in_array($entry['page'], $refreshPages, true)) {
                [$c, $u, $r] = $this->refreshLessonQuiz($lesson, $entry['questions']);
                $created += $c;
                $updated += $u;
                $removed += $r;

                continue;
            }

            $position = (int) $lesson->questions()->max('position');
            foreach ($entry['questions'] as $q) {
                if ($lesson->questions()->where('question', $q['q'])->exists()) {
                    continue;
                }
                $this->createQuestion($lesson, $q, ++$position);
                $created++;
            }
        }

        $this->line("Perguntas criadas: {$created}");
        if ($refreshPages) {
            $this->line("Perguntas atualizadas: {$updated} · removidas: {$removed}");
        }
        foreach ($missing as $m) {
            $this->warn("  aula não encontrada para o quiz: {$m}");
        }
    }

    /**
     * Reaplica a prova de uma aula. Se alguém já entregou a prova, só atualiza
     * as perguntas de mesmo texto e adiciona as novas: nada é removido nem
     * reordenado, para não mexer em respostas já dadas.
     *
     * @return array{0: int, 1: int, 2: int} criadas, atualizadas, removidas
     */
    private function refreshLessonQuiz(Lesson $lesson, array $questions): array
    {
        $created = $updated = $removed = 0;
        $locked = LessonQuizSubmission::withoutGlobalScopes()->where('lesson_id', $lesson->id)->exists();

        foreach (array_values($questions) as $i => $q) {
            $existing = $lesson->questions()->where('question', $q['q'])->first();

            if (! $existing) {
                $this->createQuestion($lesson, $q, $locked ? (int) $lesson->questions()->max('position') + 1 : $i + 1);
                $created++;

                continue;
            }

            $existing->fill([
                'options' => array_map(fn ($t) => ['text' => $t], $q['options']),
                'correct_index' => (int) $q['correct'],
                'explanation' => $q['why'] ?? null,
            ]);

            if (! $locked) {
                $existing->position = $i + 1;
            }

            if ($existing->isDirty()) {
                $existing->save();
                $updated++;
            }
        }

        $texts = collect($questions)->pluck('q')->all();
        $obsoletas = $lesson->questions()->whereNotIn('question', $texts)->get();

        if ($locked) {
            if ($obsoletas->isNotEmpty()) {
                $this->warn("  {$lesson->title}: {$obsoletas->count()} pergunta(s) antiga(s) mantida(s), porque já há prova entregue.");
            }
        } else {
            foreach ($obsoletas as $obsoleta) {
                $obsoleta->delete();
                $removed++;
            }
        }

        return [$created, $updated, $removed];
    }

    private function createQuestion(Lesson $lesson, array $q, int $position): void
    {
        LessonQuestion::create([
            'lesson_id' => $lesson->id,
            'question' => $q['q'],
            'options' => array_map(fn ($t) => ['text' => $t], $q['options']),
            'correct_index' => (int) $q['correct'],
            'explanation' => $q['why'] ?? null,
            'position' => $position,
        ]);
    }
}
