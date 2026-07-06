<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class ChecklistGroup extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_checklist_groups';

    protected static ?string $saasPermissionSlug = 'grupo_checklist';

    protected static ?string $saasModuleLabel = 'Grupos de Checklist';

    protected $fillable = [
        'name',
        'tenant_id',
        'description',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = Auth::user()?->tenant_id
                                    ?? filament()->getTenant()?->id
                                    ?? session('tenant_id');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Itens do checklist basico deste grupo (is_template=true, sem OS
     * vinculada) -- o "ponto de partida" copiado (snapshot) sempre que uma
     * OS/checklist e gerada para um ativo deste grupo.
     */
    public function templateItems(): HasMany
    {
        return $this->hasMany(MaintenanceOrderChecklist::class, 'checklist_group_id')->where('is_template', true);
    }
}
