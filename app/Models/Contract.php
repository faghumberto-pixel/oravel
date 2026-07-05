<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Contract extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_contracts';

    protected static ?string $saasPermissionSlug = 'contrato';

    protected static ?string $saasModuleLabel = 'Contratos';

    protected $fillable = [
        'tenant_id',
        'client_id',
        'asset_id',
        'contract_number',
        'start_date',
        'end_date',
        'price',
        'payment_method',
        'usage_purpose',
        'required_nrs',
        'prohibit_sublease',
        'maintenance_clause',
        'initial_horimeter',
        'initial_odometer',
        'cep_obra',
        'latitude_obra',
        'longitude_obra',
        'legal_forum',
        'insurance_details',
        'is_active',
        'status',
        'observations',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'price' => 'decimal:2',
        'initial_horimeter' => 'decimal:2',
        'initial_odometer' => 'decimal:2',
        'prohibit_sublease' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id) && Auth::check()) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }
}
