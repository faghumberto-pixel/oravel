<?php

namespace App\Livewire\Academy;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonAnswer;
use App\Models\LessonProgress;
use App\Models\LessonQuestion;
use App\Services\AcademyCertificates;
use App\Services\AcademyOverview;
use App\Services\AcademyPoints;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tela do curso: cabecalho com progresso/nota, lista de aulas e o "player" da aula escolhida
 * (video, texto, links, quiz, concluir). So' abre curso publicado E liberado no contrato.
 */
#[Layout('academy.layout')]
class CoursePage extends Component
{
    public string $slug;

    #[Url(as: 'aula')]
    public ?string $lessonId = null;

    /** @var array<string, int> */
    public array $selected = [];

    /** @var array<string, array{correct: bool, gained: int}> */
    public array $feedback = [];

    public ?string $toast = null;

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $course = $this->course();

        $lessons = $course->lessons()->available()->get();
        if (! $this->lessonId || ! $lessons->contains('id', $this->lessonId)) {
            $summary = app(AcademyOverview::class)->courses(auth()->user())->firstWhere('slug', $slug);
            $this->lessonId = $summary['next_lesson_id'] ?? $lessons->first()?->id;
        }
    }

    private function course(): Course
    {
        return Course::published()->available()->where('slug', $this->slug)->firstOrFail();
    }

    private function lesson(string $id): Lesson
    {
        return Lesson::available()->where('course_id', $this->course()->id)->findOrFail($id);
    }

    public function selectLesson(string $id): void
    {
        $this->lessonId = $this->lesson($id)->id;
        $this->toast = null;
    }

    public function toggleDone(string $id): void
    {
        $user = auth()->user();
        $lesson = $this->lesson($id);

        if (! $user->tenant_id) {
            $this->toast = 'Entre com um usuário de cliente para registrar progresso.';

            return;
        }

        $existing = LessonProgress::where('user_id', $user->id)->where('lesson_id', $lesson->id)->first();
        if ($existing) {
            $existing->delete();
        } else {
            LessonProgress::create(['user_id' => $user->id, 'lesson_id' => $lesson->id, 'completed_at' => now()]);
            $gained = app(AcademyPoints::class)->awardRead($user, $lesson);
            $this->toast = $gained > 0 ? "+{$gained} pontos" : null;
            $this->issueCertificate();
        }

        $this->dispatch('academy-updated');
    }

    public function answer(string $questionId): void
    {
        $user = auth()->user();
        $question = LessonQuestion::whereIn('lesson_id', $this->course()->lessons()->available()->pluck('id'))->findOrFail($questionId);

        if (! $user->tenant_id) {
            $this->toast = 'Entre com um usuário de cliente para responder.';

            return;
        }
        if (! isset($this->selected[$questionId])) {
            return;
        }

        [$correct, $gained] = app(AcademyPoints::class)->answer($user, $question, (int) $this->selected[$questionId]);
        $this->feedback[$questionId] = ['correct' => $correct, 'gained' => $gained];
        $this->toast = $correct && $gained ? "+{$gained} pontos" : null;
        $this->issueCertificate();
        $this->dispatch('academy-updated');
    }

    /** Batida de tempo ativo: so' pra aula aberta no momento. */
    public function heartbeat(): void
    {
        $user = auth()->user();
        if ($user->tenant_id && $this->lessonId && ($lesson = Lesson::available()->where('course_id', $this->course()->id)->find($this->lessonId))) {
            app(AcademyPoints::class)->heartbeat($user, $lesson);
        }
    }

    private function issueCertificate(): void
    {
        $service = app(AcademyCertificates::class);
        $had = (bool) $service->find(auth()->user(), $this->course());

        if (! $had && $service->issueIfEligible(auth()->user(), $this->course())) {
            $this->toast = '🎓 Certificado emitido! Baixe no topo desta página.';
        }
    }

    public function downloadCertificate()
    {
        $certificate = app(AcademyCertificates::class)->find(auth()->user(), $this->course());
        abort_unless($certificate, 404);

        return response()->streamDownload(
            fn () => print (app(AcademyCertificates::class)->pdf($certificate)),
            'certificado-'.Str::slug($certificate->course_title).'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function render()
    {
        $user = auth()->user();
        $course = $this->course();
        $lessons = $course->lessons()->available()->with('questions')->get();
        $done = LessonProgress::where('user_id', $user->id)->whereIn('lesson_id', $lessons->pluck('id'))->pluck('completed_at', 'lesson_id');
        $answered = LessonAnswer::where('user_id', $user->id)->pluck('is_correct', 'question_id');
        $summary = app(AcademyOverview::class)->courses($user)->firstWhere('slug', $this->slug);
        $current = $lessons->firstWhere('id', $this->lessonId) ?? $lessons->first();
        $index = $lessons->search(fn ($l) => $l->id === $current?->id);

        return view('livewire.academy.course', [
            'course' => $course,
            'lessons' => $lessons,
            'done' => $done,
            'answered' => $answered,
            'summary' => $summary,
            'current' => $current,
            'previous' => $index > 0 ? $lessons[$index - 1] : null,
            'next' => $index !== false && $index < $lessons->count() - 1 ? $lessons[$index + 1] : null,
            'certificate' => $summary['certificate'] ?? null,
            'readOnly' => ! $user->tenant_id,
        ]);
    }
}
