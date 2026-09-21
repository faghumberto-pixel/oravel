<?php

namespace App\Listeners;

use App\Support\ImageDownscaler;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Depois que o Media Library grava o arquivo (e ANTES das miniaturas), reduz a foto se a coleção
 * estiver na lista de permissão de config/uploads.php (só o que não é evidência). Nunca derruba o
 * upload: qualquer falha só vira log e o arquivo original é mantido.
 */
class DownscaleNonEvidenceMedia
{
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $media = $event->media;

        try {
            if (! config('uploads.downscale.enabled')
                || $media->hasCustomProperty('downscaled') // idempotente: nunca recomprime
                || ! self::isNonEvidence($media)
                || ! in_array($media->mime_type, self::IMAGE_MIMES, true)) {
                return;
            }

            $path = $media->getPath();
            if (! is_file($path)) {
                return; // disco remoto: não mexe
            }

            $result = ImageDownscaler::downscale(
                $path,
                (int) config('uploads.downscale.max_side'),
                (int) config('uploads.downscale.quality'),
                (int) config('uploads.downscale.min_bytes'),
            );
            if ($result === null) {
                return;
            }

            $media->size = $result['to_bytes'];
            $media->setCustomProperty('downscaled', [
                'from' => $result['from'], 'to' => $result['to'], 'from_bytes' => $result['from_bytes'],
            ]);
            $media->saveQuietly();
        } catch (\Throwable $e) {
            Log::warning('DownscaleNonEvidenceMedia: falhou, arquivo original mantido', [
                'media' => $media->id, 'erro' => $e->getMessage(),
            ]);
        }
    }

    /** Lista de PERMISSÃO: só coleções explicitamente liberadas em config/uploads.php. */
    public static function isNonEvidence(Media $media): bool
    {
        $modelClass = Relation::getMorphedModel($media->model_type) ?? $media->model_type;
        $allowed = config('uploads.downscale.collections', [])[$modelClass] ?? [];

        return in_array($media->collection_name, $allowed, true);
    }
}
