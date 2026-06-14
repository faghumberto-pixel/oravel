<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ChatMessage extends Model implements HasMedia
{
    use HasUuids;
    use InteractsWithMedia;
    use \App\Traits\BelongsToTenant;

    /**
     * Mantido como $guarded = [] seguindo o padrão já adotado em OrderMessage,
     * já que tenant_id/chat_room_id/user_id são sempre setados pelo backend
     * (nunca recebidos diretamente de input do usuário).
     */
    protected $guarded = [];

    protected $casts = [
        'is_forwarded' => 'boolean',
    ];

    public function chatRoom(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
