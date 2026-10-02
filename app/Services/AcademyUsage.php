<?php

namespace App\Services;

use App\Models\AcademyPoint;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonQuizSubmission;
use App\Models\UserActivityLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Uso da Academia por usuário num período (visão da Central): entradas, aulas lidas, provas
 * entregues (com nota) e tempo de estudo. O tempo vem das batidas da aula (academy_points.seconds,
 * acumulado por aula e atribuído ao período pela última batida), não da navegação.
 */
class AcademyUsage
{
    /**
     * @param  list<string>  $userIds
     * @return array<string, array{visits:int, last_visit:?CarbonInterface, lessons:int, quizzes:int, correct:int, questions:int, seconds:int}>
     */
    public static function summary(array $userIds, CarbonInterface $since, ?string $tenantId = null): array
    {
        $out = [];
        $blank = ['visits' => 0, 'last_visit' => null, 'lessons' => 0, 'quizzes' => 0, 'correct' => 0, 'questions' => 0, 'seconds' => 0];
        foreach ($userIds as $id) {
            $out[$id] = $blank;
        }
        if ($userIds === []) {
            return $out;
        }

        $scope = fn ($q) => $q->withoutGlobalScopes()->whereIn('user_id', $userIds)->when($tenantId, fn ($w) => $w->where('tenant_id', $tenantId));

        $scope(UserActivityLog::query())->where('path', 'like', '/academia%')->where('action', 'view')->where('created_at', '>=', $since)
            ->selectRaw('user_id, count(*) as n, max(created_at) as last_at')->groupBy('user_id')->get()
            ->each(function ($r) use (&$out) {
                $out[$r->user_id]['visits'] = (int) $r->n;
                $out[$r->user_id]['last_visit'] = Carbon::parse($r->last_at);
            });

        $scope(LessonProgress::query())->whereNotNull('completed_at')->where('completed_at', '>=', $since)
            ->selectRaw('user_id, count(*) as n')->groupBy('user_id')->get()
            ->each(function ($r) use (&$out) {
                $out[$r->user_id]['lessons'] = (int) $r->n;
            });

        $scope(LessonQuizSubmission::query())->where('delivered_at', '>=', $since)
            ->selectRaw('user_id, count(*) as n, sum(correct_answers) as ok, sum(total_questions) as total')->groupBy('user_id')->get()
            ->each(function ($r) use (&$out) {
                $out[$r->user_id]['quizzes'] = (int) $r->n;
                $out[$r->user_id]['correct'] = (int) $r->ok;
                $out[$r->user_id]['questions'] = (int) $r->total;
            });

        $scope(AcademyPoint::query())->where('source', AcademyPoint::TIME)->where('last_beat_at', '>=', $since)
            ->selectRaw('user_id, sum(seconds) as s')->groupBy('user_id')->get()
            ->each(function ($r) use (&$out) {
                $out[$r->user_id]['seconds'] = (int) $r->s;
            });

        return $out;
    }

    /**
     * Aula a aula do usuário no período: tempo, se concluiu a leitura e a nota da prova.
     *
     * @return Collection<int, array{course:string, lesson:string, seconds:int, read_at:?CarbonInterface, quiz:?string, quiz_at:?CarbonInterface}>
     */
    public static function lessons(string $userId, CarbonInterface $since, ?string $tenantId = null): Collection
    {
        $q = fn ($query) => $query->withoutGlobalScopes()->where('user_id', $userId)->when($tenantId, fn ($w) => $w->where('tenant_id', $tenantId));

        $time = $q(AcademyPoint::query())->where('source', AcademyPoint::TIME)->where('last_beat_at', '>=', $since)->pluck('seconds', 'ref_id');
        $read = $q(LessonProgress::query())->whereNotNull('completed_at')->where('completed_at', '>=', $since)->pluck('completed_at', 'lesson_id');
        $quiz = $q(LessonQuizSubmission::query())->where('delivered_at', '>=', $since)->get()->keyBy('lesson_id');

        $ids = $time->keys()->merge($read->keys())->merge($quiz->keys())->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return Lesson::withoutGlobalScopes()->with('course:id,title')->whereIn('id', $ids)->get()
            ->map(fn (Lesson $l) => [
                'course' => (string) $l->course?->title,
                'lesson' => $l->title,
                'seconds' => (int) ($time[$l->id] ?? 0),
                'read_at' => isset($read[$l->id]) ? Carbon::parse($read[$l->id]) : null,
                'quiz' => isset($quiz[$l->id]) ? $quiz[$l->id]->correct_answers.'/'.$quiz[$l->id]->total_questions.' acertos' : null,
                'quiz_at' => isset($quiz[$l->id]) ? $quiz[$l->id]->delivered_at : null,
            ])
            ->sortBy([['course', 'asc'], ['lesson', 'asc']])
            ->values();
    }
}
