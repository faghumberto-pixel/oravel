<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Auth;

class MaintenanceOrderMaterial extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'maintenance_order_id',
        'name',
        'quantity',
        'tenant_id'
    ];

    /**
     * Gatilho de segurança para garantir o tenant_id.
     */
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id) && Auth::check()) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    public function maintenanceOrder(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }
}