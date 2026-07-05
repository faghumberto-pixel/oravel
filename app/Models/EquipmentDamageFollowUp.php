<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentDamageFollowUp extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    public const CHANNEL_TELEFONE = 'telefone';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_PRESENCIAL = 'presencial';

    protected $fillable = [
        'tenant_id',
        'equipment_damage_id',
        'user_id',
        'contact_date',
        'channel',
        'summary',
        'next_action',
        'next_action_date',
    ];

    protected $casts = [
        'contact_date' => 'datetime',
        'next_action_date' => 'date',
    ];

    public function equipmentDamage(): BelongsTo
    {
        return $this->belongsTo(EquipmentDamage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
