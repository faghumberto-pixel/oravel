<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** Contagem fisica (inventario) de um almoxarifado, opcionalmente so' de um grupo e/ou corredor. */
class StockCount extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    /** Uma chave de contrato so' para todo o "Estoque estruturado" (grupos, enderecos, alocacoes e contagens): a Central liga junto. */
    protected static ?string $saasFeatureKey = 'tabela_stock_structure';

    protected static ?string $saasPermissionSlug = 'contagem_estoque';

    protected static ?string $saasModuleLabel = 'Contagens Físicas do Estoque';

    public const EM_CONTAGEM = 'em_contagem';

    public const FINALIZADA = 'finalizada';

    protected $fillable = ['tenant_id', 'internal_unit_id', 'stock_group_id', 'aisle', 'name', 'status', 'notes', 'created_by_user_id', 'finished_at'];

    protected $casts = ['finished_at' => 'datetime'];

    public function internalUnit(): BelongsTo
    {
        return $this->belongsTo(InternalUnit::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StockGroup::class, 'stock_group_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class);
    }

    public function aberta(): bool
    {
        return $this->status === self::EM_CONTAGEM;
    }
}
