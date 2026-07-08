<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Documentos do motorista alem da CNH (que fica em colunas proprias em
 * FleetDriver) -- MOPP, certificado de operador de equipamento, etc.
 * Mesmo padrao de FleetVehicleDocument.
 */
class FleetDriverDocument extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;

    public const TIPO_MOPP = 'mopp';

    public const TIPO_CERTIFICADO_OPERADOR = 'certificado_operador';

    public const TIPO_OUTRO = 'outro';

    protected $fillable = [
        'tenant_id',
        'fleet_driver_id',
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

    public function fleetDriver(): BelongsTo
    {
        return $this->belongsTo(FleetDriver::class);
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
