<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Item agregado: acessório que acompanha o equipamento na locação (bandeja de
 * contenção, cabo, mangueira...) mas tem controle próprio. Não é Asset
 * (máquina/equipamento) nem Material/Part (peça de reposição consumida).
 */
class AggregateItem extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_DISPONIVEL = 'disponivel';

    public const STATUS_LOCADO = 'locado';

    public const STATUS_MANUTENCAO = 'manutencao';

    public const STATUS_BAIXADO = 'baixado';

    protected static ?string $saasFeatureKey = 'tabela_aggregate_items';

    protected static ?string $saasPermissionSlug = 'item_agregado';

    protected static ?string $saasModuleLabel = 'Itens Agregados';

    protected $fillable = [
        'tenant_id', 'aggregate_item_type_id', 'code', 'description', 'serial_number',
        'status', 'condition', 'asset_id', 'supplier_id', 'purchase_date', 'purchase_value',
        'invoice_number', 'warranty_until', 'next_inspection_date', 'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_until' => 'date',
        'next_inspection_date' => 'date',
        'purchase_value' => 'decimal:2',
    ];

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_DISPONIVEL => 'Disponível',
            self::STATUS_LOCADO => 'Locado',
            self::STATUS_MANUTENCAO => 'Em manutenção',
            self::STATUS_BAIXADO => 'Baixado',
        ];
    }

    /** @return array<string, string> */
    public static function conditionLabels(): array
    {
        return ['novo' => 'Novo', 'bom' => 'Bom', 'regular' => 'Regular', 'ruim' => 'Ruim'];
    }

    protected static function booted(): void
    {
        // Sem vencimento digitado, calcula pelo intervalo padrão do tipo.
        static::creating(function (AggregateItem $item) {
            if ($item->next_inspection_date) {
                return;
            }

            $days = AggregateItemType::withoutGlobalScopes()
                ->whereKey($item->aggregate_item_type_id)
                ->value('inspection_interval_days');

            if ($days) {
                $item->next_inspection_date = now()->addDays((int) $days)->toDateString();
            }
        });
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AggregateItemType::class, 'aggregate_item_type_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
