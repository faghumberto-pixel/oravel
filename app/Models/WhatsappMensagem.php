<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMensagem extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const ENTRADA = 'entrada';

    public const SAIDA = 'saida';

    protected $table = 'whatsapp_mensagens';

    protected $fillable = [
        'tenant_id', 'conversa_id', 'direcao', 'tipo', 'corpo', 'wa_id', 'status', 'erro',
        'enviada_por_user_id', 'related_type', 'related_id', 'evento',
    ];

    public function conversa(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversa::class, 'conversa_id');
    }

    public function enviadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviada_por_user_id');
    }
}
