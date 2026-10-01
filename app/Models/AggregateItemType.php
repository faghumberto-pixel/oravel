<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AggregateItemType extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    use SoftDeletes;

    protected static ?string $saasFeatureKey = 'tabela_aggregate_item_types';

    protected static ?string $saasPermissionSlug = 'tipo_item_agregado';

    protected static ?string $saasModuleLabel = 'Tipos de Item Agregado';

    protected $fillable = ['tenant_id', 'name', 'description', 'inspection_interval_days'];

    protected $casts = ['inspection_interval_days' => 'integer'];

    public function entries(): HasMany
    {
        return $this->hasMany(AggregateItemEntry::class);
    }

    public function exits(): HasMany
    {
        return $this->hasMany(AggregateItemExit::class);
    }

    /** Saldo do inventário: entradas - saídas + devolvidas em estado OK. */
    public function balance(): int
    {
        return (int) $this->entries()->sum('quantity')
            - (int) $this->exits()->sum('quantity')
            + (int) $this->exits()->where('returned', true)
                ->where('returned_condition', AggregateItemExit::CONDITION_OK)->sum('quantity');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AggregateItem::class);
    }
}
