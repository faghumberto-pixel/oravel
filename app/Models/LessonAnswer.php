<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Resposta de um usuario a uma pergunta do quiz. Do TENANT.
 */
class LessonAnswer extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'user_id', 'question_id', 'selected_index', 'is_correct', 'attempts'];

    protected $casts = ['is_correct' => 'boolean'];
}
