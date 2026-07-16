<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pessoa de contato dentro de um prospect (empresa) -- um CrmLead pode
 * ter varios. Sem Resource proprio (sem HasSaaSMetadata, mesmo caso de
 * MaintenanceDueAlert/MaintenanceOrderPendencia), gerenciado via
 * RelationManager dentro do CrmLeadResource.
 */
class CrmLeadContact extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'crm_lead_id',
        'name',
        'role_title',
        'phone',
        'email',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }
}
