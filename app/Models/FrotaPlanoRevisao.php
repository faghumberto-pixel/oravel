<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Item de revisão preventiva de um veículo: vence por km, por dias, ou pelo que ocorrer primeiro. */
class FrotaPlanoRevisao extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_planos_revisao';

    protected static ?string $saasPermissionSlug = 'plano_revisao_frota';

    protected static ?string $saasModuleLabel = 'Revisões da Frota';

    /** Aviso de revisão próxima: até 1.000 km ou 30 dias do vencimento. */
    public const AVISO_KM = 1000;

    public const AVISO_DIAS = 30;

    protected $table = 'frota_planos_revisao';

    protected $attributes = ['ativo' => true];

    protected $fillable = ['tenant_id', 'ativo_id', 'nome', 'intervalo_km', 'intervalo_dias', 'observacoes', 'ativo'];

    protected $casts = ['ativo' => 'boolean', 'intervalo_km' => 'integer', 'intervalo_dias' => 'integer'];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }

    public function realizadas(): HasMany
    {
        return $this->hasMany(FrotaRevisaoRealizada::class, 'plano_id')->orderByDesc('realizada_em')->orderByDesc('odometro');
    }

    public function ultima(): ?FrotaRevisaoRealizada
    {
        return FrotaRevisaoRealizada::where('plano_id', $this->id)->orderByDesc('realizada_em')->orderByDesc('odometro')->first();
    }
}
