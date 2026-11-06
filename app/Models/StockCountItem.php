<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Linha da folha de contagem: um item em um endereco, com o que o sistema esperava e o que foi contado. */
class StockCountItem extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    /** Uma chave de contrato so' para todo o "Estoque estruturado" (grupos, enderecos, alocacoes e contagens): a Central liga junto. */
    protected static ?string $saasFeatureKey = 'tabela_stock_structure';

    protected static ?string $saasPermissionSlug = 'item_contagem_estoque';

    protected static ?string $saasModuleLabel = 'Itens da Contagem Física';

    protected $fillable = [
        'tenant_id', 'stock_count_id', 'stock_location_id', 'location_code', 'item_type', 'item_id', 'item_name', 'item_sku', 'group_name',
        'expected_quantity', 'counted_quantity', 'note', 'counted_at', 'counted_by_user_id', 'counted_by_name',
    ];

    protected $casts = ['expected_quantity' => 'decimal:3', 'counted_quantity' => 'decimal:3', 'counted_at' => 'datetime'];

    public function count(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(StockCountEntry::class)->orderByDesc('counted_at');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    /** Diferenca (contado - esperado); null enquanto nao contou. */
    public function diferenca(): ?float
    {
        return $this->counted_quantity === null ? null : round((float) $this->counted_quantity - (float) $this->expected_quantity, 3);
    }
}
