<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportedProblem extends Model
{
    use \App\Traits\BelongsToTenant;
    use HasUuids;

    protected $fillable = ['description', 'tenant_id'];

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class);
    }
}