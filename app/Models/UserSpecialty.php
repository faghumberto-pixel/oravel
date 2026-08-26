<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot fina User<->especialidade (ver migration user_specialty). Sem
 * tenant_id proprio -- User ja e' tenant-scoped.
 */
class UserSpecialty extends Model
{
    protected $table = 'user_specialty';

    protected $fillable = [
        'user_id',
        'specialty',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
