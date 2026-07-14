<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class EquipmentMovement extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;
    use InteractsWithMedia;

    public const TYPE_MOBILIZACAO = 'mobilizacao';

    public const TYPE_DESMOBILIZACAO = 'desmobilizacao';

    public const STATUS_AGUARDANDO_VISTORIA = 'aguardando_vistoria';

    public const STATUS_EM_ANDAMENTO = 'em_andamento';

    /**
     * Operador completou 100% do checklist, mas o patio ainda nao deu o OK
     * tecnico -- equipamento fica travado (QR invalido) ate a aprovacao.
     * So usado no fluxo de despacho de locacao (solicitacao_locacao_id);
     * a mobilizacao/desmobilizacao de OS de manutencao continua indo direto
     * pra STATUS_CONCLUIDO, sem essa segunda etapa.
     */
    public const STATUS_AGUARDANDO_APROVACAO = 'aguardando_aprovacao';

    public const STATUS_CONCLUIDO = 'concluido';

    protected static ?string $saasFeatureKey = 'tabela_equipment_movements';

    protected static ?string $saasPermissionSlug = 'movimentacao_equipamento';

    protected static ?string $saasModuleLabel = 'Mobilização / Desmobilização';

    protected $fillable = [
        'tenant_id',
        'maintenance_order_id',
        'solicitacao_locacao_id',
        'asset_id',
        'fleet_vehicle_id',
        'fleet_driver_id',
        'km_inicial',
        'km_final',
        'scheduled_at',
        'type',
        'status',
        'vistoria_geral_lat',
        'vistoria_geral_lng',
        'vistoria_geral_captured_at',
        'notes',
        'started_at',
        'completed_at',
        'approved_by_user_id',
        'approved_at',
        'rejected_reason',
        'qr_token',
        'custo_transporte',
        'client_signature',
    ];

    protected $casts = [
        'vistoria_geral_lat' => 'decimal:8',
        'vistoria_geral_lng' => 'decimal:8',
        'vistoria_geral_captured_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'custo_transporte' => 'decimal:2',
        'km_inicial' => 'decimal:2',
        'km_final' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (EquipmentMovement $movement) {
            $movement->qr_token ??= (string) Str::uuid();
        });
    }

    /**
     * Recalcula custo_transporte a partir dos FreightRecord vinculados
     * (cada um ja soma seus proprios pedagios via getCustoTotalAttribute),
     * e propaga pro logistics_cost da MaintenanceOrder (soma de todas as
     * movimentacoes dessa OS). Chamado pelos Observers de FreightRecord/
     * FleetTollRecord -- nunca editado a mao.
     */
    public function recalculateCustoTransporte(): void
    {
        $total = $this->freightRecords()->get()->sum(fn (FreightRecord $freight) => $freight->custo_total);

        $this->updateQuietly(['custo_transporte' => $total]);

        if ($this->maintenance_order_id) {
            $logisticsCost = static::where('maintenance_order_id', $this->maintenance_order_id)->sum('custo_transporte');

            MaintenanceOrder::where('id', $this->maintenance_order_id)
                ->update(['logistics_cost' => $logisticsCost]);
        }

        // Mesmo rollup, pro lado do Despacho de Locacao -- ate 2026-07-14
        // esse custo ficava preso aqui e nunca chegava em nenhum relatorio
        // financeiro (SolicitacaoLocacao nao tem maintenance_order_id).
        if ($this->solicitacao_locacao_id) {
            $logisticsCost = static::where('solicitacao_locacao_id', $this->solicitacao_locacao_id)->sum('custo_transporte');

            SolicitacaoLocacao::where('id', $this->solicitacao_locacao_id)
                ->update(['logistics_cost' => $logisticsCost]);
        }
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('vistoria_geral')->singleFile();
    }

    /**
     * Null enquanto o veiculo nao tiver km_inicial (atribuicao) e km_final
     * (finalize()) preenchidos -- nunca armazenado, so' calculado, pra nao
     * duplicar a mesma informacao de 2 jeitos que podem dessincronizar.
     */
    public function getKmPercorridoAttribute(): ?float
    {
        if ($this->km_inicial === null || $this->km_final === null) {
            return null;
        }

        return (float) $this->km_final - (float) $this->km_inicial;
    }

    /**
     * % de itens marcados -- usado tanto pelo app do operador (trava o
     * "Finalizar" abaixo de 100%) quanto pela tela de auditoria do gestor.
     */
    public function progressPercent(): int
    {
        $items = $this->items;

        if ($items->isEmpty()) {
            return 0;
        }

        return (int) round($items->where('is_checked', true)->count() / $items->count() * 100);
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function solicitacaoLocacao(): BelongsTo
    {
        return $this->belongsTo(SolicitacaoLocacao::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function fleetVehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class);
    }

    public function fleetDriver(): BelongsTo
    {
        return $this->belongsTo(FleetDriver::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EquipmentMovementItem::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(EquipmentMovementLocation::class)->orderBy('captured_at');
    }

    public function patioArrival(): HasOne
    {
        return $this->hasOne(EquipmentPatioArrival::class);
    }

    public function damages(): HasMany
    {
        return $this->hasMany(EquipmentDamage::class);
    }

    public function freightRecords(): HasMany
    {
        return $this->hasMany(FreightRecord::class);
    }

    /**
     * Presentes so quando este movement faz parte de uma operacao de troca
     * de equipamento (EquipmentReplacement) -- usado pelo mobile pra saber
     * se deve exigir assinatura do cliente ao finalizar uma mobilizacao.
     */
    public function replacementAsDesmobilization(): HasOne
    {
        return $this->hasOne(EquipmentReplacement::class, 'desmobilization_movement_id');
    }

    public function replacementAsMobilization(): HasOne
    {
        return $this->hasOne(EquipmentReplacement::class, 'mobilization_movement_id');
    }
}
