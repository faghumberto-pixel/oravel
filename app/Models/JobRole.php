<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cadastro de tipos/funcoes de colaborador (Vendedor, Tecnico, Analista...),
 * por tenant. Livre pra cada tenant criar/editar/apagar o proprio -- nao e'
 * mais uma lista fixa no codigo (ver migration 2026_09_27_210000 pro seed
 * inicial de cada tenant existente).
 */
class JobRole extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_job_roles';

    protected static ?string $saasPermissionSlug = 'funcao';

    protected static ?string $saasModuleLabel = 'Funções e Cargos';

    protected $fillable = [
        'tenant_id',
        'name',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
