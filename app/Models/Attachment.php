<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Attachment extends Model
{
    use \App\Traits\BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'maintenance_order_id', 'tenant_id', 'file_path', 'file_name', 'mime_type', 'evidence_type',
        'category', 'severity', 'observation',
        'latitude', 'longitude', 'address', 'captured_at'
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    protected static function booted()
    {
        static::creating(function ($attachment) {
            if ($attachment->latitude && $attachment->longitude) {
                $attachment->address = $attachment->fetchAddressFromCoordinates();
            }
        });
    }

    /**
     * Busca o endereço real via API Nominatim (Gratuita e sem necessidade de chave para demos)
     */
    public function fetchAddressFromCoordinates()
    {
        try {
            $response = Http::get('https://nominatim.openstreetmap.org/reverse', [
                'format' => 'jsonv2',
                'lat' => $this->latitude,
                'lon' => $this->longitude,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['display_name'] ?? 'Endereço não encontrado';
            }
        } catch (\Exception $e) {
            Log::error("Erro Geocoding: " . $e->getMessage());
        }
        return 'Localização: ' . $this->latitude . ', ' . $this->longitude;
    }
}