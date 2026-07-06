<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EquipmentReplacement extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    public const URGENCY_NORMAL = 'normal';

    public const URGENCY_URGENTE = 'urgente';

    public const URGENCY_CRITICO = 'critico';

    public const STATUS_SOLICITADO = 'solicitado';

    public const STATUS_SUBSTITUTO_IDENTIFICADO = 'substituto_identificado';

    public const STATUS_DESMOBILIZACAO_ANDAMENTO = 'desmobilizacao_andamento';

    public const STATUS_MOBILIZACAO_ANDAMENTO = 'mobilizacao_andamento';

    public const STATUS_CONCLUIDO = 'concluido';

    public const STATUS_CANCELADO = 'cancelado';

    public const ROLE_LOGISTICA = 'Gerente de Logística';

    protected static ?string $saasFeatureKey = 'tabela_equipment_replacements';

    protected static ?string $saasPermissionSlug = 'troca_equipamento';

    protected static ?string $saasModuleLabel = 'Troca de Equipamento';

    protected $fillable = [
        'tenant_id',
        'maintenance_order_id',
        'equipment_damage_id',
        'original_asset_id',
        'replacement_asset_id',
        'contract_id',
        'requested_by_user_id',
        'urgency',
        'reason',
        'status',
        'identified_by_user_id',
        'identified_at',
        'desmobilization_movement_id',
        'mobilization_movement_id',
        'completed_at',
    ];

    protected $casts = [
        'identified_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_SOLICITADO,
        'urgency' => self::URGENCY_NORMAL,
    ];

    /**
     * Resolve o contrato ativo do ativo original ja na criacao (nao so no
     * completeSwap()) -- assim o historico do contrato mostra a troca desde
     * "solicitado", nao so quando ela conclui.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if ($model->contract_id) {
                return;
            }

            $model->contract_id = Contract::where('asset_id', $model->original_asset_id)
                ->where('is_active', true)
                ->latest()
                ->value('id');
        });
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function equipmentDamage(): BelongsTo
    {
        return $this->belongsTo(EquipmentDamage::class);
    }

    public function originalAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'original_asset_id');
    }

    public function replacementAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'replacement_asset_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function identifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'identified_by_user_id');
    }

    public function desmobilizationMovement(): BelongsTo
    {
        return $this->belongsTo(EquipmentMovement::class, 'desmobilization_movement_id');
    }

    public function mobilizationMovement(): BelongsTo
    {
        return $this->belongsTo(EquipmentMovement::class, 'mobilization_movement_id');
    }

    /**
     * Data/hora exata da entrega do substituto ao cliente -- nao duplicada
     * aqui, e sempre o completed_at do movement de mobilizacao (ja e um
     * timestamp completo).
     */
    public function getDeliveredAtAttribute(): ?Carbon
    {
        return $this->mobilizationMovement?->completed_at;
    }

    /**
     * Idem para a assinatura: vive no movement de mobilizacao, nao duplicada
     * numa coluna propria de equipment_replacements.
     */
    public function getClientSignatureAttribute(): ?string
    {
        return $this->mobilizationMovement?->client_signature;
    }

    public function identifyReplacement(Asset $replacementAsset, User $identifiedBy): void
    {
        $this->update([
            'replacement_asset_id' => $replacementAsset->id,
            'identified_by_user_id' => $identifiedBy->id,
            'identified_at' => now(),
            'status' => self::STATUS_SUBSTITUTO_IDENTIFICADO,
        ]);
    }

    /**
     * Cria os dois movements (ja existentes no modulo de Logistica) e os
     * vincula um ao outro como uma unica operacao de troca. So roda uma vez
     * -- chamadas repetidas sao no-op pra nao duplicar movimentacoes.
     */
    public function startLogisticsMovements(): void
    {
        if ($this->desmobilization_movement_id || $this->mobilization_movement_id) {
            return;
        }

        if (! $this->replacement_asset_id) {
            return;
        }

        $desmobilization = EquipmentMovement::create([
            'tenant_id' => $this->tenant_id,
            'maintenance_order_id' => $this->maintenance_order_id,
            'asset_id' => $this->original_asset_id,
            'type' => EquipmentMovement::TYPE_DESMOBILIZACAO,
        ]);

        $mobilization = EquipmentMovement::create([
            'tenant_id' => $this->tenant_id,
            'maintenance_order_id' => $this->maintenance_order_id,
            'asset_id' => $this->replacement_asset_id,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
        ]);

        $this->update([
            'desmobilization_movement_id' => $desmobilization->id,
            'mobilization_movement_id' => $mobilization->id,
            'status' => self::STATUS_DESMOBILIZACAO_ANDAMENTO,
        ]);
    }

    /**
     * Reavalia o status conjunto a partir do estado atual dos dois movements
     * -- chamado pelo EquipmentMovementObserver toda vez que um dos dois
     * conclui. So marca concluido quando a mobilizacao do substituto ja tem
     * assinatura do cliente (nao basta o movement estar "concluido").
     */
    public function syncStatusFromMovements(): void
    {
        if (in_array($this->status, [self::STATUS_CONCLUIDO, self::STATUS_CANCELADO], true)) {
            return;
        }

        $desmobilization = $this->desmobilizationMovement;
        $mobilization = $this->mobilizationMovement;

        if (! $desmobilization || ! $mobilization) {
            return;
        }

        $mobilizationDone = $mobilization->status === EquipmentMovement::STATUS_CONCLUIDO;
        $desmobilizationDone = $desmobilization->status === EquipmentMovement::STATUS_CONCLUIDO;

        if ($mobilizationDone && filled($mobilization->client_signature)) {
            $this->completeSwap();

            return;
        }

        $this->update([
            'status' => $desmobilizationDone
                ? self::STATUS_MOBILIZACAO_ANDAMENTO
                : self::STATUS_DESMOBILIZACAO_ANDAMENTO,
        ]);
    }

    /**
     * Efeito colateral do fim da troca: o contrato (resolvido na criacao,
     * ver booted()) passa a apontar pro ativo substituto -- o
     * ContractObserver, ao detectar a mudanca de asset_id, libera o ativo
     * original e trava o substituto no cliente. Sem contrato (ativo
     * proprio, sem locacao), troca o status/cliente direto nos ativos.
     */
    public function completeSwap(): void
    {
        if ($this->status === self::STATUS_CONCLUIDO) {
            return;
        }

        DB::transaction(function () {
            if ($this->contract) {
                $this->contract->update(['asset_id' => $this->replacement_asset_id]);
            } else {
                $clientId = $this->originalAsset?->client_id;

                $this->originalAsset?->update(['status' => Asset::STATUS_DISPONIVEL, 'client_id' => null]);
                $this->replacementAsset?->update(['status' => Asset::STATUS_LOCADO, 'client_id' => $clientId]);
            }

            $this->update([
                'status' => self::STATUS_CONCLUIDO,
                'completed_at' => now(),
            ]);
        });
    }
}
