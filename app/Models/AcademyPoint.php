<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pontos da Academia: uma linha por (usuario, origem, referencia). Do TENANT.
 */
class AcademyPoint extends Model
{
    use BelongsToTenant, HasUuids;

    public const READ = 'read';

    public const QUIZ = 'quiz';

    public const TIME = 'time';

    public const COURSE = 'course';

    protected $fillable = ['tenant_id', 'user_id', 'source', 'ref_id', 'points', 'seconds', 'last_beat_at'];

    protected $casts = ['last_beat_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
