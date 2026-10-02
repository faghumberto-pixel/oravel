<?php

namespace App\Services;

use App\Models\AcademyCertificate;
use App\Models\AcademyPoint;
use App\Models\Course;
use App\Models\LessonAnswer;
use App\Models\LessonProgress;
use App\Models\LessonQuestion;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Certificado de conclusao: emitido quando o usuario conclui TODAS as aulas liberadas pro contrato
 * dele naquele curso E acerta todas as perguntas do quiz dessas aulas. Uma vez emitido, vale
 * (nao e' retirado se ele desmarcar uma aula depois).
 */
class AcademyCertificates
{
    public function eligible(User $user, Course $course): bool
    {
        $lessonIds = $course->lessons()->available()->pluck('id');
        if ($lessonIds->isEmpty()) {
            return false;
        }

        $done = LessonProgress::where('user_id', $user->id)->whereIn('lesson_id', $lessonIds)->count();
        if ($done < $lessonIds->count()) {
            return false;
        }

        $questionIds = LessonQuestion::whereIn('lesson_id', $lessonIds)->pluck('id');
        if ($questionIds->isEmpty()) {
            return true;
        }

        return LessonAnswer::where('user_id', $user->id)->whereIn('question_id', $questionIds)->where('is_correct', true)->count() >= $questionIds->count();
    }

    public function find(User $user, Course $course): ?AcademyCertificate
    {
        return AcademyCertificate::where('user_id', $user->id)->where('course_id', $course->id)->first();
    }

    /** Emite se elegivel e ainda nao emitido. Devolve o certificado (novo ou existente) ou null. */
    public function issueIfEligible(User $user, Course $course): ?AcademyCertificate
    {
        if ($existing = $this->find($user, $course)) {
            return $existing;
        }

        if (! $user->tenant_id || ! $this->eligible($user, $course)) {
            return null;
        }

        $seconds = (int) AcademyPoint::where('user_id', $user->id)->where('source', AcademyPoint::TIME)
            ->whereIn('ref_id', $course->lessons()->pluck('id'))->sum('seconds');

        return AcademyCertificate::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'course_id' => $course->id,
            'code' => $this->newCode(),
            'user_name' => $user->name,
            'tenant_name' => (string) $user->tenant?->name,
            'course_title' => $course->title,
            'study_minutes' => intdiv($seconds, 60),
            'issued_at' => now(),
        ]);
    }

    public function pdf(AcademyCertificate $certificate): string
    {
        return Pdf::loadView('pdf.academy-certificate', [
            'c' => $certificate,
            'verifyUrl' => url('/certificado/'.$certificate->code),
        ])->setPaper('a4', 'landscape')->output();
    }

    private function newCode(): string
    {
        do {
            $code = 'OA-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (AcademyCertificate::withoutGlobalScopes()->where('code', $code)->exists());

        return $code;
    }
}
