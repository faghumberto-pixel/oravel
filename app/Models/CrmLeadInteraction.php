<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro manual de interacao com um Lead (ligacao, e-mail, whatsapp,
 * presencial). Integracao automatica de e-mail/WhatsApp foi descartada
 * nesta rodada -- so registro manual, mesmo padrao de EquipmentDamageFollowUp.
 */
class CrmLeadInteraction extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    public const CHANNEL_TELEFONE = 'telefone';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_PRESENCIAL = 'presencial';

    public const CHANNEL_OUTRO = 'outro';

    protected $fillable = [
        'tenant_id',
        'crm_lead_id',
        'user_id',
        'channel',
        'contact_date',
        'summary',
        'next_action',
        'next_followup_date',
        'stage_at_time',
    ];

    protected $casts = [
        'contact_date' => 'datetime',
        'next_followup_date' => 'date',
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
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
