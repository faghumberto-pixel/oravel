<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Coleta do óleo usado por empresa autorizada (destinação exigida pela Resolução CONAMA 362). */
class FrotaColetaOleoUsado extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_coletas_oleo_usado';

    protected static ?string $saasPermissionSlug = 'coleta_oleo_usado_frota';

    protected static ?string $saasModuleLabel = 'Coletas de Óleo Usado da Frota';

    protected $table = 'frota_coletas_oleo_usado';

    protected $fillable = ['tenant_id', 'coletada_em', 'litros', 'empresa_coletora', 'numero_documento', 'observacoes'];

    protected $casts = ['coletada_em' => 'date', 'litros' => 'integer'];

    public function trocas(): HasMany
    {
        return $this->hasMany(FrotaTrocaOleo::class, 'coleta_oleo_usado_id');
    }
}
