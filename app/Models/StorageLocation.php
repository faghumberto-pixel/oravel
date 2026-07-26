<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Posicao estruturada de planta baixa (quadrante/prateleira). Um so model
 * pros dois contextos (almoxarifado de materiais e patio de ativos) --
 * ver PlantaBaixaGrid, o componente que consome isso pros dois casos.
 */
class StorageLocation extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    public const CONTEXT_ALMOXARIFADO = 'almoxarifado';

    public const CONTEXT_PATIO_ATIVOS = 'patio_ativos';

    protected static ?string $saasFeatureKey = 'tabela_storage_locations';

    protected static ?string $saasPermissionSlug = 'localizacao_estoque';

    protected static ?string $saasModuleLabel = 'Planta Baixa (Localizações)';

    protected $fillable = [
        'tenant_id', 'internal_unit_id', 'context', 'code', 'label', 'row', 'column', 'is_active',
    ];

    protected $casts = [
        'row' => 'integer',
        'column' => 'integer',
        'is_active' => 'boolean',
    ];

    public static function contextOptions(): array
    {
        return [
            self::CONTEXT_ALMOXARIFADO => 'Almoxarifado (Materiais)',
            self::CONTEXT_PATIO_ATIVOS => 'Pátio de Ativos',
        ];
    }

    public function internalUnit(): BelongsTo
    {
        return $this->belongsTo(InternalUnit::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
