<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPlan extends Model
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_billing_plans';

    protected static ?string $saasPermissionSlug = 'planos_cobranca';

    protected static ?string $saasModuleLabel = 'Planos de Cobrança (Dinâmicos)';

    use BelongsToTenant, HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCY_QUARTERLY = 'quarterly';

    public const FREQUENCY_ANNUAL = 'annual';

    protected $fillable = [
        'tenant_id',
        'client_id',
        'contract_id',
        'frequency',
        'amount',
        'due_day',
        'active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_day' => 'integer',
        'active' => 'boolean',
    ];

    public static function frequencyLabels(): array
    {
        return [
            self::FREQUENCY_MONTHLY => 'Mensal',
            self::FREQUENCY_QUARTERLY => 'Trimestral',
            self::FREQUENCY_ANNUAL => 'Anual',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function accountReceivables(): HasMany
    {
        return $this->hasMany(AccountReceivable::class);
    }
}
