<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Liga um modelo de dashboard a uma empresa. */
class DashboardAssignment extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['tenant_id', 'dashboard_template_id', 'is_home'];

    protected $casts = ['is_home' => 'boolean'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DashboardTemplate::class, 'dashboard_template_id');
    }
}
