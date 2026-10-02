<?php

namespace App\Services;

use App\Models\AcademyPoint;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonAnswer;
use App\Models\LessonProgress;
use App\Models\LessonQuizSubmission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pontuacao da Academia. Regra de ouro: cada acao pontua UMA vez por usuario
 * (unique user+origem+referencia) -- rever aula, desmarcar/marcar de novo ou refazer
 * quiz nunca infla o total. Valores em config('oravel.academy').
 */
class AcademyPoints
{
    /** Batida de "estou usando a aula": so' conta se vier ritmada, e uma sessao parada nao credita. */
    private const MIN_GAP = 20;

    private const MAX_CREDIT = 35;

    private const SESSION_BREAK = 90;

    /** Total de pontos do usuario. */
    public function total(User $user): int
    {
        return (int) AcademyPoint::where('user_id', $user->id)->sum('points');
    }

    /** @return Collection<int, object{user_id:string, name:string, points:int}> ranking do tenant do usuario */
    public function ranking(User $user, int $limit = 20)
    {
        return AcademyPoint::query()
            ->join('users', 'users.id', '=', 'academy_points.user_id')
            ->where('academy_points.tenant_id', $user->tenant_id)
            ->groupBy('academy_points.user_id', 'users.name')
            ->selectRaw('academy_points.user_id as user_id, users.name as name, sum(academy_points.points) as points')
            ->orderByDesc('points')
            ->orderBy('users.name')
            ->limit($limit)
            ->get();
    }

    /** Concluiu a leitura da aula: pontua uma vez; se fechou o curso, bonus (uma vez). */
    public function awardRead(User $user, Lesson $lesson): int
    {
        $gained = $this->award($user, AcademyPoint::READ, $lesson->id, (int) config('oravel.academy.read'));

        return $gained + $this->maybeAwardCourse($user, $lesson->course);
    }

    /**
     * Entrega a prova (quiz) de uma aula, de uma vez. Questao em branco vale zero. Cada acerto
     * pontua (uma vez por pergunta). Depois de entregue NAO refaz -- o gabarito passa a ser
     * mostrado, entao uma segunda tentativa nao mediria mais o conhecimento. Idempotente: se ja
     * foi entregue, devolve a entrega existente sem mexer em nada.
     *
     * @param  array<string, int|string|null>  $selected  alternativa escolhida por id de pergunta
     * @return array{submission: LessonQuizSubmission, gained: int, already: bool}
     */
    public function deliver(User $user, Lesson $lesson, array $selected): array
    {
        $existing = LessonQuizSubmission::where('user_id', $user->id)->where('lesson_id', $lesson->id)->first();
        if ($existing) {
            return ['submission' => $existing, 'gained' => 0, 'already' => true];
        }

        return DB::transaction(function () use ($user, $lesson, $selected) {
            $questions = $lesson->questions()->get();
            $correct = 0;
            $gained = 0;

            foreach ($questions as $question) {
                $choice = $selected[$question->id] ?? null;
                $choice = is_numeric($choice) && (int) $choice >= 0 && (int) $choice < count($question->optionTexts()) ? (int) $choice : null;
                $isCorrect = $choice !== null && $choice === (int) $question->correct_index;

                LessonAnswer::create([
                    'tenant_id' => $user->tenant_id,
                    'user_id' => $user->id,
                    'question_id' => $question->id,
                    'selected_index' => $choice,
                    'is_correct' => $isCorrect,
                    'attempts' => 1,
                ]);

                if ($isCorrect) {
                    $correct++;
                    $gained += $this->award($user, AcademyPoint::QUIZ, $question->id, (int) config('oravel.academy.quiz'));
                }
            }

            $submission = LessonQuizSubmission::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'total_questions' => $questions->count(),
                'correct_answers' => $correct,
                'delivered_at' => now(),
            ]);

            return ['submission' => $submission, 'gained' => $gained, 'already' => false];
        });
    }

    /**
     * Batida de tempo ativo (o navegador chama a cada ~30 s com a aula aberta e a aba visivel).
     * Credita no maximo MAX_CREDIT s por batida, ignora batidas coladas (< MIN_GAP) e nao credita
     * quando houve pausa longa. Pontos = minutos ativos * valor, com teto por aula.
     */
    public function heartbeat(User $user, Lesson $lesson): int
    {
        return DB::transaction(function () use ($user, $lesson) {
            $row = AcademyPoint::where(['user_id' => $user->id, 'source' => AcademyPoint::TIME, 'ref_id' => $lesson->id])->lockForUpdate()->first()
                ?? new AcademyPoint(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'source' => AcademyPoint::TIME, 'ref_id' => $lesson->id]);

            $now = now();
            if ($row->last_beat_at) {
                $gap = (int) abs($now->diffInSeconds($row->last_beat_at, false));
                if ($gap < self::MIN_GAP) {
                    return 0; // batida colada: ignora (nao atualiza o relogio)
                }
                if ($gap <= self::SESSION_BREAK) {
                    $row->seconds += min($gap, self::MAX_CREDIT);
                }
            }
            $row->last_beat_at = $now;

            $before = (int) $row->points;
            $row->points = min(
                (int) config('oravel.academy.time_cap'),
                intdiv((int) $row->seconds, 60) * (int) config('oravel.academy.time_per_minute'),
            );
            $row->save();

            return (int) $row->points - $before;
        });
    }

    /** Bonus de curso: todas as aulas LIBERADAS pro contrato concluidas, uma vez. */
    private function maybeAwardCourse(User $user, Course $course): int
    {
        $ids = $course->lessons()->available()->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        $done = LessonProgress::where('user_id', $user->id)->whereIn('lesson_id', $ids)->count();

        return $done >= $ids->count()
            ? $this->award($user, AcademyPoint::COURSE, $course->id, (int) config('oravel.academy.course_bonus'))
            : 0;
    }

    /** Grava a linha so' se ainda nao existe (idempotente, mesmo em corrida). Devolve pontos ganhos agora. */
    private function award(User $user, string $source, string $refId, int $points): int
    {
        $row = AcademyPoint::firstOrCreate(
            ['user_id' => $user->id, 'source' => $source, 'ref_id' => $refId],
            ['tenant_id' => $user->tenant_id, 'points' => $points],
        );

        return $row->wasRecentlyCreated ? $points : 0;
    }
}
