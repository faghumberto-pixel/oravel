<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de dedupe de App\Console\Commands\CheckMaintenanceDueAlerts --
 * nao tem Resource/tela propria, e' so' controle interno do comando.
 */
class MaintenanceDueAlert extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'maintenance_plan_id',
        'alerted_at',
    ];

    protected $casts = [
        'alerted_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }
}
