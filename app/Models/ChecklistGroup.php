<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistGroup extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'tenant_id'];

    public function checklists(): HasMany
    {
        return $this->hasMany(MaintenanceOrderChecklist::class, 'checklist_group_id');
    }
}
