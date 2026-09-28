<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro manual de interação com o cliente sobre uma Proposta Comercial
 * (ligação, e-mail, whatsapp, presencial) com "próxima ação"/data de
 * follow-up -- pedido explícito do usuário 28/09/2026 ("criar laços que
 * unam o histórico das ações com o tempo e as providências que precisam
 * ser tomadas"). Mesmo padrão de CrmLeadInteraction/EquipmentDamageFollowUp.
 */
class PropostaComercialInteraction extends Model
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
        'proposta_comercial_id',
        'user_id',
        'channel',
        'contact_date',
        'summary',
        'next_action',
        'next_followup_date',
        'status_at_time',
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

    public function proposta(): BelongsTo
    {
        return $this->belongsTo(PropostaComercial::class, 'proposta_comercial_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
