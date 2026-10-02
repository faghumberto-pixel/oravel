<?php

namespace App\Services;

use App\Models\AcademyCertificate;
use App\Models\AcademyPoint;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonAnswer;
use App\Models\LessonProgress;
use App\Models\LessonQuestion;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Visao do ALUNO na Academia: pra cada curso liberado no contrato, quanto ele ja fez, a nota
 * do quiz, o tempo, os pontos e o certificado -- fonte unica da barra lateral, do inicio e da
 * tela do curso. Poucas consultas no total (nao uma por curso).
 *
 * Nota = respostas certas / perguntas JA RESPONDIDAS x 10 (null se o curso nao tem quiz ou ainda nao respondeu nenhuma).
 * Pra tirar certificado, porem, tem que acertar TODAS as perguntas (ver AcademyCertificates).
 * Status: 'todo' (nenhuma aula), 'progress' (algumas), 'done' (todas as aulas liberadas).
 */
class AcademyOverview
{
    public const STATUS_TODO = 'todo';

    public const STATUS_PROGRESS = 'progress';

    public const STATUS_DONE = 'done';

    /**
     * @return Collection<int, array<string, mixed>> um item por curso publicado e liberado
     */
    public function courses(User $user): Collection
    {
        $courses = Course::published()->available()->orderBy('position')->get();
        if ($courses->isEmpty()) {
            return collect();
        }

        $lessons = Lesson::available()->whereIn('course_id', $courses->pluck('id'))->orderBy('position')->get(['id', 'course_id', 'title', 'position'])->groupBy('course_id');
        $lessonIds = $lessons->flatten()->pluck('id');

        $done = LessonProgress::where('user_id', $user->id)->whereIn('lesson_id', $lessonIds)->pluck('completed_at', 'lesson_id');
        $questions = LessonQuestion::whereIn('lesson_id', $lessonIds)->get(['id', 'lesson_id'])->groupBy('lesson_id');
        $answers = LessonAnswer::where('user_id', $user->id)->get(['question_id', 'is_correct'])->keyBy('question_id');
        $points = AcademyPoint::where('user_id', $user->id)->get();
        $certificates = AcademyCertificate::where('user_id', $user->id)->get()->keyBy('course_id');

        return $courses->map(function (Course $course) use ($lessons, $done, $questions, $answers, $points, $certificates) {
            $courseLessons = $lessons->get($course->id, collect());
            $ids = $courseLessons->pluck('id');
            $total = $ids->count();
            $doneCount = $ids->filter(fn ($id) => $done->has($id))->count();

            $questionIds = $ids->flatMap(fn ($id) => ($questions->get($id) ?? collect())->pluck('id'));
            $quizTotal = $questionIds->count();
            $quizAnswered = $questionIds->filter(fn ($qid) => $answers->has($qid))->count();
            $quizCorrect = $questionIds->filter(fn ($qid) => $answers->get($qid)?->is_correct)->count();

            $refs = $ids->merge($questionIds)->push($course->id)->flip();
            $courseRows = $points->filter(fn ($p) => $refs->has($p->ref_id));
            $seconds = (int) $courseRows->where('source', AcademyPoint::TIME)->sum('seconds');

            $last = $ids->map(fn ($id) => $done->get($id))->filter()->max();

            return [
                'id' => $course->id,
                'slug' => $course->slug,
                'title' => $course->title,
                'description' => $course->description,
                'lessons_total' => $total,
                'lessons_done' => $doneCount,
                'percent' => $total ? (int) round($doneCount / $total * 100) : 0,
                'status' => $doneCount === 0 ? self::STATUS_TODO : ($doneCount >= $total ? self::STATUS_DONE : self::STATUS_PROGRESS),
                'quiz_total' => $quizTotal,
                'quiz_answered' => $quizAnswered,
                'quiz_correct' => $quizCorrect,
                'nota' => $quizAnswered ? round($quizCorrect / $quizAnswered * 10, 1) : null,
                'minutes' => intdiv($seconds, 60),
                'points' => (int) $courseRows->sum('points'),
                'certificate' => $certificates->get($course->id),
                'last_activity' => $last,
                'next_lesson_id' => $courseLessons->first(fn ($l) => ! $done->has($l->id))?->id ?? $courseLessons->first()?->id,
            ];
        })->values();
    }

    /**
     * Totais do aluno (cartoes do inicio).
     *
     * @param  Collection<int, array<string, mixed>>  $courses
     * @return array<string, mixed>
     */
    public function totals(User $user, Collection $courses): array
    {
        $quizAnswered = (int) $courses->sum('quiz_answered');
        $minutes = (int) $courses->sum('minutes');

        return [
            'points' => app(AcademyPoints::class)->total($user),
            'courses' => $courses->count(),
            'courses_done' => $courses->where('status', self::STATUS_DONE)->count(),
            'courses_progress' => $courses->where('status', self::STATUS_PROGRESS)->count(),
            'courses_todo' => $courses->where('status', self::STATUS_TODO)->count(),
            'lessons_done' => (int) $courses->sum('lessons_done'),
            'lessons_total' => (int) $courses->sum('lessons_total'),
            'nota' => $quizAnswered ? round($courses->sum('quiz_correct') / $quizAnswered * 10, 1) : null,
            'minutes' => $minutes,
            'certificates' => $courses->filter(fn ($c) => $c['certificate'])->count(),
            'overall_percent' => $courses->sum('lessons_total') ? (int) round($courses->sum('lessons_done') / $courses->sum('lessons_total') * 100) : 0,
        ];
    }

    /** "Continue de onde parou": o curso em andamento mexido por ultimo (ou o primeiro a fazer). */
    public function resume(Collection $courses): ?array
    {
        return $courses->where('status', self::STATUS_PROGRESS)->sortByDesc('last_activity')->first()
            ?? $courses->firstWhere('status', self::STATUS_TODO);
    }
}
