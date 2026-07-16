<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro imutavel de troca de vendedor responsavel -- gravado por
 * CrmLeadObserver::updating() quando assigned_user_id muda, nunca
 * editado/apagado pela UI. Sem tela de criacao propria, so' exibido
 * como historico dentro do Lead.
 */
class CrmLeadAssignment extends Model
{
    use BelongsToTenant;
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'crm_lead_id',
        'from_user_id',
        'to_user_id',
        'changed_by_user_id',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
