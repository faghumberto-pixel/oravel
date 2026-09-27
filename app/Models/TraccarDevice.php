<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraccarDevice extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    const UPDATED_AT = null;

    protected static ?string $saasFeatureKey = 'tabela_traccar_devices';

    protected static ?string $saasPermissionSlug = 'rastreamento_gps';

    protected static ?string $saasModuleLabel = 'Rastreamento GPS (Traccar)';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'traccar_device_id',
        'identifier',
    ];

    protected $casts = [
        'traccar_device_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
