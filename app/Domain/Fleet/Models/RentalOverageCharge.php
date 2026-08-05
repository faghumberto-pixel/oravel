<?php

namespace App\Domain\Fleet\Models;

use App\Models\AccountReceivable;
use App\Models\Asset;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalOverageCharge extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_INVOICED = 'invoiced';

    public const STATUS_CANCELLED = 'cancelled';

    protected static ?string $saasFeatureKey = 'tabela_rental_overage_charges';

    protected static ?string $saasPermissionSlug = 'excedente_locacao';

    protected static ?string $saasModuleLabel = 'Excedentes de Locação';

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'asset_id',
        'period_start',
        'period_end',
        'hours_used',
        'hours_included',
        'hours_overage',
        'amount',
        'account_receivable_id',
        'status',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'hours_used' => 'decimal:2',
        'hours_included' => 'decimal:2',
        'hours_overage' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Pendente',
            self::STATUS_INVOICED => 'Faturado',
            self::STATUS_CANCELLED => 'Cancelado',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function accountReceivable(): BelongsTo
    {
        return $this->belongsTo(AccountReceivable::class);
    }
}
