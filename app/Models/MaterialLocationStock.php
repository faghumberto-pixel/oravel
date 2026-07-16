<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saldo de um Material numa filial (InternalUnit) especifica -- fonte de
 * verdade do estoque dali pra frente. Material.current_stock continua
 * existindo como cache (soma de todas as linhas daqui por material),
 * atualizado sozinho por App\Services\MaterialStockService.
 */
class MaterialLocationStock extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_material_location_stock';

    protected static ?string $saasPermissionSlug = 'estoque_por_filial';

    protected static ?string $saasModuleLabel = 'Estoque por Filial';

    protected $table = 'material_location_stock';

    protected $fillable = [
        'tenant_id',
        'material_id',
        'internal_unit_id',
        'current_quantity',
        'minimum_threshold',
        'maximum_threshold',
    ];

    protected $casts = [
        'current_quantity' => 'integer',
        'minimum_threshold' => 'integer',
        'maximum_threshold' => 'integer',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function internalUnit(): BelongsTo
    {
        return $this->belongsTo(InternalUnit::class);
    }

    public function isLowStock(): bool
    {
        return $this->current_quantity <= $this->minimum_threshold;
    }
}
