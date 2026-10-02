<?php

namespace App\Filament\Resources\CourseResource\Pages;

use App\Filament\Resources\CourseResource;
use App\Models\AcademyCertificate;
use App\Models\Lesson;
use App\Models\LessonAnswer;
use App\Models\LessonProgress;
use App\Models\LessonQuestion;
use App\Services\AcademyCertificates;
use App\Services\AcademyPoints;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ViewCourse extends ViewRecord
{
    protected static string $resource = CourseResource::class;

    protected static string $view = 'filament.academy.view-course';

    public ?string $openLesson = null;

    /** @var array<string, int> alternativa escolhida por pergunta (antes de responder) */
    public array $selected = [];

    /** @var array<string, array{correct:bool, gained:int}> resultado por pergunta */
    public array $feedback = [];

    public function getTitle(): string
    {
        return $this->record->title;
    }

    public function lessons(): Collection
    {
        return $this->record->lessons()->available()->get();
    }

    /** @return array<string, true> ids das aulas ja concluidas pelo usuario logado */
    public function doneIds(): array
    {
        return LessonProgress::where('user_id', auth()->id())
            ->whereIn('lesson_id', $this->record->lessons()->available()->pluck('id'))
            ->pluck('lesson_id')
            ->mapWithKeys(fn ($id) => [$id => true])
            ->all();
    }

    public function toggleDone(string $lessonId): void
    {
        $user = auth()->user();
        $lesson = Lesson::available()->where('course_id', $this->record->id)->findOrFail($lessonId);

        // Super admin da plataforma nao tem tenant: nao grava progresso (tenant_id seria nulo).
        if (! $user?->tenant_id) {
            Notification::make()->title('Entre com um usuário de cliente para registrar progresso.')->warning()->send();

            return;
        }

        $existing = LessonProgress::where('user_id', $user->id)->where('lesson_id', $lesson->id)->first();

        if ($existing) {
            $existing->delete();

            return;
        }

        LessonProgress::create([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'completed_at' => now(),
        ]);

        $gained = app(AcademyPoints::class)->awardRead($user, $lesson);
        if ($gained > 0) {
            Notification::make()->title("+{$gained} pontos")->success()->send();
        }
        $this->notifyIfCertified($user);
    }

    public function certificate(): ?AcademyCertificate
    {
        return auth()->user() ? app(AcademyCertificates::class)->find(auth()->user(), $this->record) : null;
    }

    private function notifyIfCertified($user): void
    {
        $service = app(AcademyCertificates::class);
        $had = (bool) $service->find($user, $this->record);

        if (! $had && $service->issueIfEligible($user, $this->record)) {
            Notification::make()->title('🎓 Certificado emitido!')->body('Baixe na página do curso.')->success()->send();
        }
    }

    public function downloadCertificate()
    {
        $certificate = $this->certificate();
        abort_unless($certificate, 404);

        return response()->streamDownload(
            fn () => print (app(AcademyCertificates::class)->pdf($certificate)),
            'certificado-'.Str::slug($certificate->course_title).'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function points(): int
    {
        return auth()->user() ? app(AcademyPoints::class)->total(auth()->user()) : 0;
    }

    /** Respostas ja dadas pelo usuario nas perguntas deste curso: [question_id => is_correct]. */
    public function answered(): array
    {
        return LessonAnswer::where('user_id', auth()->id())->pluck('is_correct', 'question_id')->all();
    }

    public function answerQuestion(string $questionId): void
    {
        $user = auth()->user();
        $question = LessonQuestion::whereIn('lesson_id', $this->record->lessons()->available()->pluck('id'))->findOrFail($questionId);

        if (! $user?->tenant_id) {
            Notification::make()->title('Entre com um usuário de cliente para responder.')->warning()->send();

            return;
        }

        if (! isset($this->selected[$questionId])) {
            return;
        }

        [$correct, $gained] = app(AcademyPoints::class)->answer($user, $question, (int) $this->selected[$questionId]);
        $this->feedback[$questionId] = ['correct' => $correct, 'gained' => $gained];
        $this->notifyIfCertified($user);
    }

    /** Batida de tempo ativo: so' vale pra aula aberta, liberada pro contrato, e de usuario de cliente. */
    public function heartbeat(string $lessonId): void
    {
        $user = auth()->user();
        if (! $user?->tenant_id || $this->openLesson !== $lessonId) {
            return;
        }

        $lesson = Lesson::available()->where('course_id', $this->record->id)->find($lessonId);
        if ($lesson) {
            app(AcademyPoints::class)->heartbeat($user, $lesson);
        }
    }

    public function open(string $lessonId): void
    {
        $this->openLesson = $this->openLesson === $lessonId ? null : $lessonId;
    }
}
