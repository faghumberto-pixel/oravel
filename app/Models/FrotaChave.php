<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Chave (ou controle) de um veículo, com o histórico de quem a retirou. */
class FrotaChave extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_chaves';

    protected static ?string $saasPermissionSlug = 'chave_frota';

    protected static ?string $saasModuleLabel = 'Chaves da Frota';

    /** Chave fora há mais de N dias = atenção. */
    public const AVISO_DIAS = 7;

    protected $table = 'frota_chaves';

    protected $attributes = ['ativo' => true];

    protected $fillable = ['tenant_id', 'ativo_id', 'identificacao', 'observacoes', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(FrotaEntregaChave::class, 'chave_id')->orderByDesc('entregue_em');
    }

    /** Retirada em aberto (a chave está com alguém), se houver. */
    public function entregaAberta(): ?FrotaEntregaChave
    {
        return FrotaEntregaChave::where('chave_id', $this->id)->whereNull('devolvida_em')->first();
    }
}
