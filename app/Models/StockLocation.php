<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Endereco do almoxarifado: corredor > prateleira > nivel > posicao, dentro de uma unidade (matriz/filial). E' onde
 * se conta fisicamente. Diferente de StorageLocation (celula da planta baixa): aqui a hierarquia e' explicita.
 */
class StockLocation extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    /** Uma chave de contrato so' para todo o "Estoque estruturado" (grupos, enderecos, alocacoes e contagens): a Central liga junto. */
    protected static ?string $saasFeatureKey = 'tabela_stock_structure';

    protected static ?string $saasPermissionSlug = 'endereco_estoque';

    protected static ?string $saasModuleLabel = 'Endereços do Almoxarifado';

    protected $fillable = ['tenant_id', 'internal_unit_id', 'aisle', 'shelf', 'level', 'position', 'code', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (self $e) {
            $e->code = static::montarCodigo($e->aisle, $e->shelf, $e->level, $e->position);
        });
    }

    /** "C-A · P-03 · N-2 · Cx-1" */
    public static function montarCodigo(?string $corredor, ?string $prateleira, ?string $nivel = null, ?string $posicao = null): string
    {
        return collect(['C' => $corredor, 'P' => $prateleira, 'N' => $nivel, 'Cx' => $posicao])
            ->filter(fn ($v) => filled($v))->map(fn ($v, $k) => $k.'-'.$v)->implode(' · ');
    }

    public function internalUnit(): BelongsTo
    {
        return $this->belongsTo(InternalUnit::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(StockPlacement::class);
    }
}
