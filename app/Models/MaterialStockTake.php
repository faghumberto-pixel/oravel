<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Cabecalho de um Inventario (contagem fisica de estoque). Nasce em
 * rascunho com uma linha (MaterialStockTakeItem) por Material do tenant,
 * com o saldo do sistema no momento em que a contagem comecou
 * (expected_quantity). Ao finalizar(), toda linha com diferenca entre
 * contado e esperado vira um MaterialStockMovement tipo ajuste_manual, e
 * o saldo do Material e' corrigido pro valor contado.
 */
class MaterialStockTake extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_material_stock_takes';

    protected static ?string $saasPermissionSlug = 'inventario';

    protected static ?string $saasModuleLabel = 'Inventário de Estoque';

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_FINALIZADO = 'finalizado';

    protected $fillable = [
        'tenant_id',
        'conducted_by_user_id',
        'status',
        'notes',
        'finished_at',
    ];

    protected $casts = [
        'finished_at' => 'datetime',
    ];

    public function conductedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialStockTakeItem::class);
    }

    /**
     * Popula uma linha por Material do tenant com o saldo atual do
     * sistema -- chamado uma vez, ao criar o inventario (nao repetir se
     * ja tiver itens, pra nao duplicar ao reabrir um rascunho).
     */
    public function populateFromMaterials(): void
    {
        if ($this->items()->exists()) {
            return;
        }

        Material::where('tenant_id', $this->tenant_id)->each(function (Material $material) {
            $this->items()->create([
                'tenant_id' => $this->tenant_id,
                'material_id' => $material->id,
                'expected_quantity' => $material->current_stock,
            ]);
        });
    }

    /**
     * So' gera ajuste pras linhas que de fato foram contadas
     * (counted_quantity preenchido) e que divergem do esperado -- linha
     * sem contagem e' ignorada (nao assume "sumiu tudo").
     */
    public function finalize(): void
    {
        if ($this->status === self::STATUS_FINALIZADO) {
            return;
        }

        DB::transaction(function () {
            $this->items()->whereNotNull('counted_quantity')->get()->each(function (MaterialStockTakeItem $item) {
                $difference = (float) $item->counted_quantity - (float) $item->expected_quantity;

                if (abs($difference) < 0.001) {
                    return;
                }

                // Material.current_stock e' coluna integer (mesmo padrao ja
                // usado em min_stock/max_stock) -- arredonda em vez de
                // truncar, pra uma contagem como 15.6 virar 16, nao 15.
                $material = $item->material;
                $material->update(['current_stock' => (int) round((float) $item->counted_quantity)]);

                StockMovement::record(
                    $material,
                    StockMovement::TYPE_AJUSTE_MANUAL,
                    $difference,
                    (float) $item->counted_quantity,
                    $this,
                    $this->conducted_by_user_id
                );
            });

            $this->update([
                'status' => self::STATUS_FINALIZADO,
                'finished_at' => now(),
            ]);
        });
    }
}
