<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitação de retirada de equipamento aberta pelo Client no Portal.
 * Sem automação de despacho -- fica em status 'solicitado' até o operador
 * acionar manualmente (ver ProgramacaoLogistica no admin).
 */
class EquipmentPickupRequest extends Model
{
    use BelongsToTenant, HasFactory, HasSaaSMetadata, HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_equipment_pickup_requests';

    protected static ?string $saasPermissionSlug = 'solicitacao_retirada';

    protected static ?string $saasModuleLabel = 'Solicitações de Retirada';

    public const STATUS_SOLICITADO = 'solicitado';

    public const STATUS_AGENDADO = 'agendado';

    public const STATUS_CONCLUIDO = 'concluido';

    protected $fillable = [
        'tenant_id', 'client_id', 'asset_id', 'contract_id',
        'status', 'notes', 'requested_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (EquipmentPickupRequest $request) {
            $request->status ??= self::STATUS_SOLICITADO;
            $request->requested_at ??= now();
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
