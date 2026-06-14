<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Filament\Facades\Filament;
use App\Models\Traits\BelongsToTenant; // Seu trait existente

class Contract extends Model
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_contracts";
    protected static ?string $saasPermissionSlug = "contrato";
    protected static ?string $saasModuleLabel = "Contratos";

    use BelongsToTenant, SoftDeletes, HasUuids;

    protected $fillable = [
        'tenant_id', 'client_id', 'asset_id', 'contract_number', 
        'start_date', 'status', 'is_active', 'price', 'observations'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $tenant = Filament::getTenant();
                if ($tenant) {
                    $model->tenant_id = $tenant->id;
                }
            }
        });
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function asset(): BelongsTo { return $this->belongsTo(Asset::class, 'asset_id'); }
}