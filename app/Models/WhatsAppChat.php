<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chat único do atendimento de WhatsApp da própria Oravel (não é
 * tenant-owned -- um único número atende todos os tenants/leads, não uma
 * linha por empresa cliente). Por isso não usa BelongsToTenant nem
 * HasSaaSMetadata, ao contrário de Client/CrmLead (que também têm
 * telefone, mas pertencem a um tenant específico).
 */
class WhatsAppChat extends Model
{
    /**
     * Nome explícito -- a convenção automática do Eloquent (Str::snake)
     * interpreta "WhatsApp" como duas palavras ("whats_app"), não bate
     * com o nome real da tabela (whatsapp_chats, sem underscore no meio).
     */
    protected $table = 'whatsapp_chats';

    public const STATUS_AI_HANDLING = 'ai_handling';

    public const STATUS_HUMAN_HANDLING = 'human_handling';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'phone_number',
        'contact_name',
        'status',
        'context_data',
    ];

    protected $casts = [
        'context_data' => 'array',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_chat_id');
    }
}
