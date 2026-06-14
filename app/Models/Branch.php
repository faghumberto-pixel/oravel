<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\BelongsToTenant;

class Branch extends Model
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_branches";
    protected static ?string $saasPermissionSlug = "filial";
    protected static ?string $saasModuleLabel = "Filiais";

    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    // 🚀 Ajustado fielmente à sua tabela: name e description
    protected $fillable = [
        'name',
        'description',
        'city',    // Adicionado
        'state',   // Adicionado
        'tenant_id'
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function accountPayables(): HasMany
    {
        return $this->hasMany(AccountPayable::class, 'branch_id');
    }
}