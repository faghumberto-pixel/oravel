<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ChatMessage extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia; // Ativa as mídias aqui!

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
