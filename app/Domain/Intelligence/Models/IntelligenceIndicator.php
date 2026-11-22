<?php

namespace App\Domain\Intelligence\Models;

use App\Domain\Intelligence\Concerns\Imutavel;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Valor de um indicador calculado de um snapshot (uma linha por indicador: indicador novo não exige migration). */
class IntelligenceIndicator extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    use Imutavel;

    protected $table = 'intelligence_indicators';

    protected static ?string $saasFeatureKey = 'tabela_intelligence_indicators';

    protected static ?string $saasPermissionSlug = 'intelligence_indicator';

    protected static ?string $saasModuleLabel = 'Intelligence - Indicadores';

    protected $fillable = ['tenant_id', 'snapshot_id', 'chave', 'valor', 'unidade', 'meta', 'created_at'];

    protected $casts = ['created_at' => 'datetime', 'valor' => 'decimal:4', 'meta' => 'array'];
}
