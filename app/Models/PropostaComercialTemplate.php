<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cabeçalho de termos padrão, tenant-scoped -- cada locadora que contrata
 * o Oravel define seus próprios textos. Ao criar uma PropostaComercial,
 * default_terms é COPIADO para terms, não referenciado (ver
 * PropostaComercial::fillFromTemplate()) -- editar o template depois não
 * altera propostas já criadas.
 */
class PropostaComercialTemplate extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_proposta_comercial';

    protected static ?string $saasPermissionSlug = 'proposta_comercial_template';

    protected static ?string $saasModuleLabel = 'Templates de Proposta Comercial';

    protected $fillable = [
        'tenant_id',
        'name',
        'is_active',
        'is_default',
        'default_terms',
        'default_valid_days',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'default_valid_days' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
