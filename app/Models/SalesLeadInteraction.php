<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro manual de interacao com um SalesLead -- mesmo papel de
 * CrmLeadInteraction, dominio Central em vez de por tenant.
 */
class SalesLeadInteraction extends Model
{
    use HasFactory;
    use HasUuids;

    public const CHANNEL_TELEFONE = 'telefone';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_PRESENCIAL = 'presencial';

    public const CHANNEL_OUTRO = 'outro';

    protected $fillable = [
        'sales_lead_id',
        'user_id',
        'channel',
        'contact_date',
        'summary',
        'stage_at_time',
    ];

    protected $casts = [
        'contact_date' => 'datetime',
    ];

    public static function channelLabels(): array
    {
        return [
            self::CHANNEL_TELEFONE => 'Telefone',
            self::CHANNEL_EMAIL => 'E-mail',
            self::CHANNEL_WHATSAPP => 'WhatsApp',
            self::CHANNEL_PRESENCIAL => 'Presencial',
            self::CHANNEL_OUTRO => 'Outro',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
