<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Passagem em pedágio de um veículo. Histórico: não se edita nem se apaga. */
class FrotaPedagio extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_pedagios';

    protected static ?string $saasPermissionSlug = 'pedagio_frota';

    protected static ?string $saasModuleLabel = 'Pedágios da Frota';

    protected $table = 'frota_pedagios';

    protected $fillable = ['tenant_id', 'ativo_id', 'tag_id', 'motorista_id', 'saida_veiculo_id', 'passou_em', 'local', 'valor', 'observacoes', 'registrado_por'];

    protected $casts = ['passou_em' => 'datetime', 'valor' => 'decimal:2'];

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(FleetDriver::class, 'motorista_id');
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(FrotaTag::class, 'tag_id');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }
}
