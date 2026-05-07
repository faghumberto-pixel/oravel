<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class InternalCommunication extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'maintenance_order_id', 
        'user_id', 
        'message', 
        'tenant_id'
    ];

    /**
     * Preenchimento automático do tenant_id antes de salvar
     */
    protected static function booted()
    {
        static::creating(function ($communication) {
            if (empty($communication->tenant_id) && Auth::check()) {
                $communication->tenant_id = Auth::user()->tenant_id;
            }
            if (empty($communication->user_id) && Auth::check()) {
                $communication->user_id = Auth::id();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }
}