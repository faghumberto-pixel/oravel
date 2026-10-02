<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pergunta de multipla escolha de uma Lesson. Global (sem tenant_id), como a aula.
 */
class LessonQuestion extends Model
{
    use HasUuids;

    protected $fillable = ['lesson_id', 'question', 'options', 'correct_index', 'explanation', 'position'];

    protected $casts = ['options' => 'array', 'correct_index' => 'integer'];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** Textos das alternativas, na ordem. */
    public function optionTexts(): array
    {
        return collect($this->options ?? [])->map(fn ($o) => is_array($o) ? (string) ($o['text'] ?? '') : (string) $o)->values()->all();
    }
}
