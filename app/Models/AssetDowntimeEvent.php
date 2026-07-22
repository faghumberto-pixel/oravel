<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class AssetDowntimeEvent extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    public const REASON_QUEBRA = 'quebra';

    public const REASON_MANUTENCAO_PREVENTIVA = 'manutencao_preventiva';

    public const REASON_MANUTENCAO_CORRETIVA = 'manutencao_corretiva';

    public const REASON_AGUARDANDO_PECA = 'aguardando_peca';

    public const REASON_OCIOSO_SEM_USO = 'ocioso_sem_uso';

    public const REASON_OUTRO = 'outro';

    protected static ?string $saasFeatureKey = 'tabela_asset_downtime_events';

    protected static ?string $saasPermissionSlug = 'parada_ativo';

    protected static ?string $saasModuleLabel = 'Histórico de Paradas';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'started_at',
        'ended_at',
        'reason',
        'maintenance_order_id',
        'notes',
        'registered_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * @return array<string, string>
     */
    public static function reasonLabels(): array
    {
        return [
            self::REASON_QUEBRA => 'Quebra',
            self::REASON_MANUTENCAO_PREVENTIVA => 'Manutenção Preventiva',
            self::REASON_MANUTENCAO_CORRETIVA => 'Manutenção Corretiva',
            self::REASON_AGUARDANDO_PECA => 'Aguardando Peça',
            self::REASON_OCIOSO_SEM_USO => 'Ocioso / Sem Uso',
            self::REASON_OUTRO => 'Outro',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /**
     * Diferença entre started_at e ended_at -- se ainda em aberto, até
     * agora. Sempre em minutos inteiros pra evitar o mesmo bug de
     * diffInSeconds() já documentado em MaintenanceOrder (Carbon 3 mudou o
     * default de $absolute pra false).
     */
    public function getDurationAttribute(): int
    {
        $end = $this->ended_at ?? Carbon::now();

        return (int) $this->started_at->diffInMinutes($end, true);
    }

    public function close(): void
    {
        if ($this->ended_at) {
            return;
        }

        $this->update(['ended_at' => now()]);
    }
}
