<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Checkpoint manual de localizacao durante o transporte de uma
 * movimentacao. Registro filho de EquipmentMovement -- sem HasSaaSMetadata/
 * Policy propria, mesmo padrao de EquipmentMovementItem (acesso flui pela
 * policy do pai).
 */
class EquipmentMovementLocation extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const CHECKPOINT_SAIDA_PATIO = 'saida_patio';

    public const CHECKPOINT_CHECKPOINT = 'checkpoint';

    public const CHECKPOINT_CHEGADA_DESTINO = 'chegada_destino';

    public const CHECKPOINT_SAIDA_CLIENTE = 'saida_cliente';

    public const CHECKPOINT_CHEGADA_PATIO = 'chegada_patio';

    protected $fillable = [
        'tenant_id',
        'equipment_movement_id',
        'checkpoint_type',
        'latitude',
        'longitude',
        'address',
        'captured_at',
        'captured_by_user_id',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'captured_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (EquipmentMovementLocation $location) {
            if ($location->latitude && $location->longitude && ! $location->address) {
                $location->address = $location->fetchAddressFromCoordinates();
            }
        });
    }

    /**
     * Mesma API gratuita (Nominatim/OpenStreetMap) ja usada em Attachment
     * pra transformar lat/lng em endereco legivel, sem custo de chave.
     */
    public function fetchAddressFromCoordinates(): ?string
    {
        try {
            $response = Http::get('https://nominatim.openstreetmap.org/reverse', [
                'format' => 'jsonv2',
                'lat' => $this->latitude,
                'lon' => $this->longitude,
            ]);

            if ($response->successful()) {
                return $response->json('display_name');
            }
        } catch (\Exception $e) {
            Log::error('Erro Geocoding (EquipmentMovementLocation): '.$e->getMessage());
        }

        return null;
    }

    public function equipmentMovement(): BelongsTo
    {
        return $this->belongsTo(EquipmentMovement::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }
}
