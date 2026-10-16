<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Registro manual de interacao com um Lead (ligacao, e-mail, whatsapp,
 * presencial). Integracao automatica de e-mail/WhatsApp foi descartada
 * nesta rodada -- so registro manual, mesmo padrao de EquipmentDamageFollowUp.
 */
class CrmLeadInteraction extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;

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
        'direcao',
        'contact_date',
        'summary',
        'transcricao',
        'next_action',
        'next_followup_date',
        'stage_at_time',
    ];

    protected $casts = [
        'contact_date' => 'datetime',
        'next_followup_date' => 'date',
    ];

    /** Áudio da conversa (WhatsApp) em disco privado: só abre por link assinado temporário. */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('audio')
            ->useDisk('media_private')
            ->singleFile()
            ->acceptsMimeTypes(['audio/ogg', 'audio/opus', 'audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/webm', 'video/webm', 'audio/wav', 'audio/x-wav']);
    }

    public static function direcaoLabels(): array
    {
        return ['entrada' => 'Recebida', 'saida' => 'Enviada'];
    }

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
