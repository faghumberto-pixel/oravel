<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistTemplate extends Model
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_checklist_templates";
    protected static ?string $saasPermissionSlug = "checklist";
    protected static ?string $saasModuleLabel = "Checklists";

    // A instrução do Trait foi devidamente movida para dentro das chaves da classe
    use \App\Models\Traits\BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'is_active'
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
