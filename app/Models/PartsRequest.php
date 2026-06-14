<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Auth;

class PartsRequest extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_parts_requests";
    protected static ?string $saasPermissionSlug = "solicitacao_pecas";
    protected static ?string $saasModuleLabel = "Solicitacao de Pecas";

    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'maintenance_order_id',
        'user_id',
        'part_description',
        'quantity',
        'status',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            // Injeção automática e segura do tenant_id para isolamento dos dados
            if (empty($model->tenant_id)) {
                $model->tenant_id = Auth::user()?->tenant_id 
                                    ?? filament()->getTenant()?->id 
                                    ?? session('tenant_id');
            }
        });
    }

    /**
     * Relacionamento com a empresa (Multi-tenancy)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relacionamento com a Ordem de Serviço
     */
    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    /**
     * Relacionamento com o Usuário (Técnico que solicitou)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
