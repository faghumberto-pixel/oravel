<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class FleetVehicleDocument extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;

    /**
     * Vocabulario real de tipo de documento -- usado no form do
     * FleetVehicleResource (aba de documentacao).
     */
    public const TIPO_CRLV = 'crlv';

    public const TIPO_SEGURO = 'seguro';

    public const TIPO_TACOGRAFO = 'tacografo';

    public const TIPO_ANTT_RNTRC = 'antt_rntrc';

    public const TIPO_OUTRO = 'outro';

    protected $fillable = [
        'tenant_id',
        'fleet_vehicle_id',
        'tipo',
        'data_emissao',
        'data_vencimento',
        'observacoes',
    ];

    protected $casts = [
        'data_emissao' => 'date',
        'data_vencimento' => 'date',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('arquivo')->singleFile();
    }

    public function fleetVehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class);
    }

    public function isVencido(): bool
    {
        return $this->data_vencimento && $this->data_vencimento->isPast();
    }

    public function isProximoVencimento(int $dias = 30): bool
    {
        return $this->data_vencimento
            && ! $this->isVencido()
            && $this->data_vencimento->lessThanOrEqualTo(now()->addDays($dias));
    }
}
