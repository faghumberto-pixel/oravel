<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Plano de troca de óleo de um veículo: a troca vence por km ou por dias, o que ocorrer primeiro. */
class FrotaPlanoOleo extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_planos_oleo';

    protected static ?string $saasPermissionSlug = 'plano_oleo_frota';

    protected static ?string $saasModuleLabel = 'Planos de Óleo da Frota';

    protected $table = 'frota_planos_oleo';

    protected $attributes = ['ativo' => true];

    protected $fillable = ['tenant_id', 'ativo_id', 'intervalo_km', 'intervalo_dias', 'especificacao_oleo', 'capacidade_litros', 'observacao_filtros', 'ativo'];

    protected $casts = ['ativo' => 'boolean', 'capacidade_litros' => 'integer', 'intervalo_km' => 'integer', 'intervalo_dias' => 'integer'];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }
}
