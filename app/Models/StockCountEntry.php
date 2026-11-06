<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Um registro de contagem: quem contou, quanto, quando e por qual canal (web ou celular). */
class StockCountEntry extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_stock_structure';

    protected static ?string $saasPermissionSlug = 'registro_contagem_estoque';

    protected static ?string $saasModuleLabel = 'Histórico da Contagem Física';

    protected $fillable = ['tenant_id', 'stock_count_item_id', 'quantity', 'counted_by_user_id', 'counted_by_name', 'source', 'note', 'counted_at'];

    protected $casts = ['quantity' => 'decimal:3', 'counted_at' => 'datetime'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockCountItem::class, 'stock_count_item_id');
    }
}
