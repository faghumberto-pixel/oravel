<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Configuração de SLA do tenant: status internos que pausam o relógio e o % do prazo a partir do qual fica "em risco". */
class SlaSetting extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['tenant_id', 'pause_statuses', 'warn_pct', 'escalate_pct', 'calendar_mode', 'timezone', 'business_hours'];

    protected $casts = [
        'pause_statuses' => 'array',
        'warn_pct' => 'integer',
        'escalate_pct' => 'integer',
        'business_hours' => 'array',
    ];
}
