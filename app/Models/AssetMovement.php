<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMovement extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'maintenance_order_id',
        'contract_id',
        'technician_id',
        'movement_type',
        'from_location',
        'to_location',
        'reason',
        'moved_at',
        'checklist_items',
        'equipment_photos_before',
        'equipment_photos_after',
        'horimeter_reading_start',
        'horimeter_reading_end',
        'fuel_level_start',
        'fuel_level_end',
        'observations',
        'voice_notes',
        'signature_data',
        'divergences',
        'sync_status',
        'synced_at',
        'completed_at',
    ];

    protected $casts = [
        'moved_at' => 'datetime',
        'synced_at' => 'datetime',
        'completed_at' => 'datetime',
        'checklist_items' => 'array',
        'equipment_photos_before' => 'array',
        'equipment_photos_after' => 'array',
        'divergences' => 'array',
    ];

    /**
     * RELAÇÕES
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
