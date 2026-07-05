<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FreightCarrier extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_freight_carriers';

    protected static ?string $saasPermissionSlug = 'transportadora';

    protected static ?string $saasModuleLabel = 'Transportadoras';

    protected $fillable = [
        'tenant_id',
        'nome',
        'documento',
        'contato_nome',
        'contato_telefone',
    ];

    public function freightRecords(): HasMany
    {
        return $this->hasMany(FreightRecord::class);
    }
}
