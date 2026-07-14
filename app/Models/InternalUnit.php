<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class InternalUnit extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_internal_units';

    protected static ?string $saasPermissionSlug = 'unidade_interna';

    protected static ?string $saasModuleLabel = 'Unidades Internas';

    /**
     * cep/address/city/state/latitude/longitude ja existiam na tabela
     * (migrations add_geoloc_fields_to_internal_units_table.php) e ja
     * apareciam no form do InternalUnitResource -- mas como nao estavam
     * aqui, o mass-assignment descartava tudo silenciosamente ao salvar.
     */
    protected $fillable = [
        'name', 'tenant_id', 'description',
        'cep', 'address', 'city', 'state', 'latitude', 'longitude',
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
     * O vínculo que o Filament está cobrando
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
