<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use App\Services\MaterialStockService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Cabecalho de um Inventario (contagem fisica de estoque) -- por filial
 * (internal_unit_id), ja que uma contagem fisica so faz sentido num lugar
 * por vez. Nasce em rascunho com uma linha (MaterialStockTakeItem) por
 * Material do tenant, com o saldo do sistema NAQUELA filial no momento em
 * que a contagem comecou (expected_quantity). Ao finalizar(), toda linha
 * com diferenca entre contado e esperado vira um MaterialStockMovement
 * tipo ajuste_manual via MaterialStockService, e o saldo da filial e'
 * corrigido pro valor contado.
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
        'internal_unit_id',
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

    public function internalUnit(): BelongsTo
    {
        return $this->belongsTo(InternalUnit::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialStockTakeItem::class);
    }

    /**
     * Popula uma linha por Material do tenant com o saldo atual do
     * sistema NESSA FILIAL -- chamado uma vez, ao criar o inventario (nao
     * repetir se ja tiver itens, pra nao duplicar ao reabrir um rascunho).
     * Material sem linha de estoque ainda nessa filial conta como 0
     * esperado (nunca movimentou por la').
     */
    public function populateFromMaterials(): void
    {
        if ($this->items()->exists()) {
            return;
        }

        $saldosPorMaterial = MaterialLocationStock::where('internal_unit_id', $this->internal_unit_id)
            ->pluck('current_quantity', 'material_id');

        Material::where('tenant_id', $this->tenant_id)->each(function (Material $material) use ($saldosPorMaterial) {
            $this->items()->create([
                'tenant_id' => $this->tenant_id,
                'material_id' => $material->id,
                'expected_quantity' => $saldosPorMaterial->get($material->id, 0),
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
            $unit = $this->internalUnit;

            $this->items()->whereNotNull('counted_quantity')->get()->each(function (MaterialStockTakeItem $item) use ($unit) {
                $difference = (float) $item->counted_quantity - (float) $item->expected_quantity;

                if (abs($difference) < 0.001) {
                    return;
                }

                app(MaterialStockService::class)->adjust(
                    $item->material,
                    $unit,
                    (float) $item->counted_quantity,
                    $this->conducted_by_user_id,
                    $this
                );
            });

            $this->update([
                'status' => self::STATUS_FINALIZADO,
                'finished_at' => now(),
            ]);
        });
    }
}
