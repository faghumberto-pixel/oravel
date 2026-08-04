<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\HorimeterReading;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Cria um HorimeterReading + upload da foto, compartilhado entre
 * HourMeterSyncController (técnico logado, mobile offline em lote) e
 * HourMeterPublicController (funcionário do cliente locatário, link
 * público sem login, um apontamento por vez) -- mesma regra de negócio,
 * mesmo destino de foto (GCS com fallback local), duas superfícies de
 * autenticação bem diferentes.
 */
class HourMeterReadingWriter
{
    private const GCS_DISK = 'gcs';

    private const FALLBACK_DISK = 'public';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Asset $asset, array $payload, ?UploadedFile $photo): HorimeterReading
    {
        $photoPath = $photo ? $this->uploadPhoto($photo, $asset->tenant_id) : null;

        try {
            return HorimeterReading::create([
                'tenant_id' => $asset->tenant_id,
                'asset_id' => $asset->id,
                'reading' => $payload['reading'],
                'recorded_at' => $payload['recorded_at'],
                'recorded_by' => $payload['recorded_by'] ?? null,
                'recorded_by_name' => $payload['recorded_by_name'] ?? null,
                'source' => $payload['source'],
                'reset_confirmed' => $payload['reset_confirmed'] ?? false,
                'notes' => $payload['notes'] ?? null,
                'photo_path' => $photoPath,
            ]);
        } catch (Throwable $e) {
            if ($photoPath) {
                $this->photoDisk()->delete($photoPath);
            }

            throw $e;
        }
    }

    public function uploadPhoto(UploadedFile $photo, string $tenantId): string
    {
        return $photo->store("horimeter-readings/{$tenantId}", $this->photoDiskName());
    }

    public function photoDisk(): Filesystem
    {
        return Storage::disk($this->photoDiskName());
    }

    /**
     * GCS só é usado se o driver estiver de fato registrado (pacote
     * league/flysystem-google-cloud-storage instalado + credenciais no
     * .env). Sem isso, cai pro disk local 'public' em vez de quebrar o
     * sync -- mesma foto, outro storage por trás.
     */
    public function photoDiskName(): string
    {
        try {
            Storage::disk(self::GCS_DISK)->exists('.');

            return self::GCS_DISK;
        } catch (Throwable) {
            return self::FALLBACK_DISK;
        }
    }
}
