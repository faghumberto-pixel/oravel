<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Company extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_companies';

    protected static ?string $saasPermissionSlug = 'empresa';

    protected static ?string $saasModuleLabel = 'Empresas';

    protected $fillable = [
        'name',
        'tenant_id',
        // Colunas reais desde a migration original (create_companies_table)
        // -- ficavam fora do fillable, descartadas silenciosamente em
        // qualquer mass-assignment (mesma classe de bug já documentada em
        // InternalUnit/Client), achado ao construir o Resource de verdade.
        'address',
        'city',
        'state',
        'phone',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            // Garante a injeção automática e segura do tenant_id em tempo real
            if (empty($model->tenant_id)) {
                $model->tenant_id = Auth::user()?->tenant_id
                                    ?? filament()->getTenant()?->id
                                    ?? session('tenant_id');
            }
        });
    }

    /**
     * RELAÇÃO OBRIGATÓRIA PARA O FILAMENT (Tenancy)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
