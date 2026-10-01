<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Entrada (compra) de Itens Agregados no inventário. */
class AggregateItemEntry extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    use SoftDeletes;

    protected static ?string $saasFeatureKey = 'tabela_aggregate_item_entries';

    protected static ?string $saasPermissionSlug = 'entrada_item_agregado';

    protected static ?string $saasModuleLabel = 'Entradas de Itens Agregados';

    protected $fillable = [
        'tenant_id', 'aggregate_item_type_id', 'supplier_id', 'entry_date', 'unit_price',
        'quantity', 'total', 'invoice_number', 'notes',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
        'quantity' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (AggregateItemEntry $entry) {
            $entry->total = round((float) $entry->unit_price * (int) $entry->quantity, 2);
        });
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AggregateItemType::class, 'aggregate_item_type_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
