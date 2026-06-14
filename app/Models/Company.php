<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Company extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_companies";
    protected static ?string $saasPermissionSlug = "empresa";
    protected static ?string $saasModuleLabel = "Empresas";

    protected $fillable = [
        'name',
        'tenant_id',
        // Adicione aqui os outros campos $fillable originais deste model caso existam (ex: cnpj, email, etc.)
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
