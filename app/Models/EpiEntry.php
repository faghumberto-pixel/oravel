<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use App\Services\MaterialStockService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Entrada (compra) de EPI. Ao criar, credita o estoque do Material EPI na
 * filial via MaterialStockService (mesmo ponto unico de escrita do resto do
 * sistema); a saida continua sendo EpiDelivery (entrega ao colaborador).
 */
class EpiEntry extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    use SoftDeletes;

    protected static ?string $saasFeatureKey = 'tabela_epi_entries';

    protected static ?string $saasPermissionSlug = 'entrada_epi';

    protected static ?string $saasModuleLabel = 'Entradas de EPI';

    protected $fillable = [
        'tenant_id', 'material_id', 'internal_unit_id', 'supplier_id', 'entry_date',
        'unit_price', 'quantity', 'total', 'invoice_number', 'notes',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
        'quantity' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (EpiEntry $entry) {
            $entry->total = round((float) $entry->unit_price * (int) $entry->quantity, 2);
        });

        static::created(function (EpiEntry $entry) {
            app(MaterialStockService::class)->receive(
                $entry->material,
                $entry->internalUnit,
                $entry->quantity,
                $entry,
                auth()->id(),
                $entry->invoice_number,
            );
        });
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function internalUnit(): BelongsTo
    {
        return $this->belongsTo(InternalUnit::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
