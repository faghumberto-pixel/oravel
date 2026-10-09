<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Saída de Itens Agregados: o item vai junto com um equipamento (locação,
 * reposição, OS...). Devolvido em estado OK volta ao saldo; NOK não volta.
 */
class AggregateItemExit extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    use SoftDeletes;

    public const REASON_LOCACAO = 'locacao';

    public const REASON_REPOSICAO = 'reposicao';

    public const REASON_TROCA = 'troca';

    public const REASON_ORDEM_SERVICO = 'ordem_servico';

    public const REASON_OUTRO = 'outro';

    public const CONDITION_OK = 'ok';

    public const CONDITION_NOK = 'nok';

    protected static ?string $saasFeatureKey = 'tabela_aggregate_item_exits';

    protected static ?string $saasPermissionSlug = 'saida_item_agregado';

    protected static ?string $saasModuleLabel = 'Saídas de Itens Agregados';

    protected $fillable = [
        'tenant_id', 'aggregate_item_type_id', 'aggregate_item_id', 'asset_id', 'contract_id', 'unit_cost', 'maintenance_order_id',
        'exit_date', 'quantity', 'reason', 'returned', 'returned_at', 'returned_condition', 'notes',
    ];

    protected $casts = [
        'exit_date' => 'date',
        'returned_at' => 'date',
        'returned' => 'boolean',
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    /** @return array<string, string> */
    public static function reasonLabels(): array
    {
        return [
            self::REASON_LOCACAO => 'Locação',
            self::REASON_REPOSICAO => 'Reposição',
            self::REASON_TROCA => 'Troca',
            self::REASON_ORDEM_SERVICO => 'Ordem de Serviço',
            self::REASON_OUTRO => 'Outro',
        ];
    }

    /** @return array<string, string> */
    public static function conditionLabels(): array
    {
        return [self::CONDITION_OK => 'OK', self::CONDITION_NOK => 'Não OK'];
    }

    protected static function booted(): void
    {
        // Custo unitário congelado na saída: valor de compra da unidade específica ou, no estoque
        // por quantidade, o preço médio das entradas do tipo. Contrato: o ativo vigente do destino.
        static::creating(function (AggregateItemExit $exit) {
            if ($exit->unit_cost === null) {
                $exit->unit_cost = $exit->item?->purchase_value
                    ?? AggregateItemEntry::where('aggregate_item_type_id', $exit->aggregate_item_type_id)
                        ->where('quantity', '>', 0)
                        ->selectRaw('SUM(unit_price * quantity) / SUM(quantity) as avg_cost')
                        ->value('avg_cost');
            }

            if (blank($exit->contract_id) && $exit->asset_id) {
                $exit->contract_id = Contract::where('asset_id', $exit->asset_id)
                    ->where('status', 'Ativo')->latest('start_date')->value('id');
            }
        });

        // Saída de uma unidade específica: ela passa a acompanhar o equipamento.
        static::created(function (AggregateItemExit $exit) {
            $exit->item?->update([
                'status' => AggregateItem::STATUS_LOCADO,
                'asset_id' => $exit->asset_id,
            ]);
        });
    }

    public function registerReturn(string $condition, ?string $notes = null): void
    {
        $this->update([
            'returned' => true,
            'returned_at' => now()->toDateString(),
            'returned_condition' => $condition,
            'notes' => trim(($this->notes ? $this->notes."\n" : '').($notes ?? '')) ?: null,
        ]);

        // A unidade volta ao estoque (OK) ou vai pra manutenção (não OK).
        $this->item?->update([
            'status' => $condition === self::CONDITION_OK ? AggregateItem::STATUS_DISPONIVEL : AggregateItem::STATUS_MANUTENCAO,
            'asset_id' => null,
        ]);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AggregateItemType::class, 'aggregate_item_type_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AggregateItem::class, 'aggregate_item_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }
}
