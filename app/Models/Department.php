<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role;

class Department extends Model
{
    use HasSaaSMetadata;
    use HasUuids;
    use BelongsToTenant;

    protected static ?string $saasFeatureKey = "tabela_departments";
    protected static ?string $saasPermissionSlug = "departamento";
    protected static ?string $saasModuleLabel = "Departamentos";

    protected $fillable = [
        'name',
        'code',
        'tenant_id',
    ];

    /**
     * Relacionamento com a Empresa (Tenant)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Funções (Roles) vinculadas a este departamento
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'department_id');
    }

    /**
     * Funcionários (Users) alocados neste departamento
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id');
    }
}
