<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class MaintenancePlan extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_maintenance_plans";
    protected static ?string $saasPermissionSlug = "plano_manutencao";
    protected static ?string $saasModuleLabel = "Planos de Manutencao";

    use HasUuids;

    protected $guarded = [];

    protected static function booted()
    {
        static::creating(function ($model) {
            // Injeção automática e segura do tenant_id para isolamento dos dados
            if (empty($model->tenant_id)) {
                $model->tenant_id = Auth::user()?->tenant_id 
                                    ?? filament()->getTenant()?->id 
                                    ?? session('tenant_id');
            }
        });
    }

    // O Filament exige este método para o Tenancy funcionar
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
