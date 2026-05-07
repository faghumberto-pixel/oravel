<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MaintenanceOrderDelegation extends Model
{
    use HasUuids;

    protected $fillable = ['maintenance_order_id', 'technician_id', 'supervisor_instructions', 'delegated_at', 'deadline'];

    public function maintenanceOrder() { return $this->belongsTo(MaintenanceOrder::class); }
    public function technician() { return $this->belongsTo(User::class, 'technician_id'); }
}
