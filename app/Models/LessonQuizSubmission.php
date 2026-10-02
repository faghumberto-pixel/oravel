<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Prova (quiz de uma aula) entregue por um usuario. Do TENANT. Uma por usuario e aula.
 */
class LessonQuizSubmission extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'user_id', 'lesson_id', 'total_questions', 'correct_answers', 'delivered_at'];

    protected $casts = ['delivered_at' => 'datetime'];

    /** Nota da prova (0 a 10). */
    public function grade(): float
    {
        return $this->total_questions ? round($this->correct_answers / $this->total_questions * 10, 1) : 0.0;
    }
}
