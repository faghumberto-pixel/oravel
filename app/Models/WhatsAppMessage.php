<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    /**
     * Nome explícito -- a convenção automática do Eloquent (Str::snake)
     * interpreta "WhatsApp" como duas palavras ("whats_app"), não bate
     * com o nome real da tabela (whatsapp_messages, sem underscore no meio).
     */
    protected $table = 'whatsapp_messages';

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_SYSTEM = 'system';

    protected $fillable = [
        'whatsapp_chat_id',
        'role',
        'content',
        'message_id',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(WhatsAppChat::class, 'whatsapp_chat_id');
    }
}
