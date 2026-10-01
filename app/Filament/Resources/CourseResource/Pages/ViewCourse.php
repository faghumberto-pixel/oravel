<?php

namespace App\Filament\Resources\CourseResource\Pages;

use App\Filament\Resources\CourseResource;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;

class ViewCourse extends ViewRecord
{
    protected static string $resource = CourseResource::class;

    protected static string $view = 'filament.academy.view-course';

    public ?string $openLesson = null;

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
    }

    public function open(string $lessonId): void
    {
        $this->openLesson = $this->openLesson === $lessonId ? null : $lessonId;
    }
}
