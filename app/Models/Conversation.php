<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Conversation extends Model
{
    protected $fillable = ['name', 'is_group', 'tenant_id'];

    public function tenant(): BelongsTo {
        return $this->belongsTo(Tenant::class);
    }

    public function messages(): HasMany {
        return $this->hasMany(Message::class);
    }

    public function users(): BelongsToMany {
        return $this->belongsToMany(User::class);
    }
}