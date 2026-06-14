<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Location extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_locations";
    protected static ?string $saasPermissionSlug = "local";
    protected static ?string $saasModuleLabel = "Locais";

    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 
        'name', 
        'address', 
        'city', 
        'state', 
        'zip_code', 
        'tenant_id'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            // Garante a injeção automática do tenant_id para blindagem de dados
            if (empty($model->tenant_id)) {
                $model->tenant_id = Auth::user()?->tenant_id 
                                    ?? filament()->getTenant()?->id 
                                    ?? session('tenant_id');
            }
        });
    }

    // Relacionamento com Tenant
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
