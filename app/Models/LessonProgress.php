<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aula concluida por um usuario. Do TENANT (tenant_id + BelongsToTenant).
 */
class LessonProgress extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'lesson_progress';

    protected $fillable = ['tenant_id', 'user_id', 'lesson_id', 'completed_at'];

    protected $casts = ['completed_at' => 'datetime'];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
