<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonQuestion;
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
            if (! empty($c['description']) && (blank($course->description) || $refresh)) {
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
                if ($refresh || blank($lesson->summary)) {
                    $lesson->summary = $l['summary'] ?? $lesson->summary;
                }
                if (($refresh || blank($lesson->body)) && is_file($file)) {
                    $lesson->body = (string) file_get_contents($file) ?: null;
                }
                $lesson->save();
            }
        }

        if (! $dry && ! $this->option('no-quiz') && is_file($dir.'/quiz.json')) {
            $this->importQuiz($dir.'/quiz.json', $base);
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
    private function importQuiz(string $file, string $base): void
    {
        $created = 0;
        $missing = [];

        foreach (json_decode((string) file_get_contents($file), true) ?: [] as $entry) {
            $lesson = Lesson::where('page_url', $base.$entry['page'])->first();
            if (! $lesson) {
                $missing[] = $entry['page'];

                continue;
            }
            $position = (int) $lesson->questions()->max('position');
            foreach ($entry['questions'] as $q) {
                if ($lesson->questions()->where('question', $q['q'])->exists()) {
                    continue;
                }
                LessonQuestion::create([
                    'lesson_id' => $lesson->id,
                    'question' => $q['q'],
                    'options' => array_map(fn ($t) => ['text' => $t], $q['options']),
                    'correct_index' => (int) $q['correct'],
                    'explanation' => $q['why'] ?? null,
                    'position' => ++$position,
                ]);
                $created++;
            }
        }

        $this->line("Perguntas criadas: {$created}");
        foreach ($missing as $m) {
            $this->warn("  aula não encontrada para o quiz: {$m}");
        }
    }
}
