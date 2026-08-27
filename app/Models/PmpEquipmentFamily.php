<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalogo global (sem tenant_id, mesmo padrao de Plan) de familias de
 * equipamento pra templates de PMP -- gerenciado pelo super admin no
 * painel central, importavel por qualquer tenant via
 * MaintenancePlan::importFromFamilyTemplate().
 */
class PmpEquipmentFamily extends Model
{
    use HasUuids;

    protected $fillable = [
        'segment',
        'name',
        'description',
    ];

    public function templateItems(): HasMany
    {
        return $this->hasMany(PmpTemplateItem::class);
    }
}
