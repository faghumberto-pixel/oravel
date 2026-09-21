<?php

namespace App\Support;

/**
 * Reduz uma foto no lugar: lado maior <= $maxSide e recompressão, aplicando a orientação EXIF antes
 * de descartar os metadados (senão fotos de celular ficariam deitadas). Só jpeg/png/webp.
 *
 * Nunca piora: se o resultado não fica menor, o arquivo original é mantido intacto (devolve null).
 * Também devolve null, sem tocar no arquivo, quando não é imagem suportada, é gigante demais para a
 * memória disponível, tem orientação espelhada (não tratada) ou já é pequena.
 */
final class ImageDownscaler
{
    /** Acima disso o GD não cabe em memória com folga: não arrisca. */
    private const MAX_PIXELS = 60_000_000;

    /**
     * @return array{from_bytes: int, to_bytes: int, from: string, to: string}|null null = nada foi alterado
     */
    public static function downscale(string $path, int $maxSide = 1600, int $quality = 82, int $minBytes = 307200): ?array
    {
        $before = @filesize($path);
        $info = @getimagesize($path);
        if ($before === false || $info === false) {
            return null;
        }

        [$width, $height, $type] = $info;
        if (! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $width * $height > self::MAX_PIXELS) {
            return null;
        }

        $orientation = $type === IMAGETYPE_JPEG ? self::exifOrientation($path) : 1;
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            return null; // espelhada: não tratamos; manter o original (com EXIF) é o mais seguro
        }
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
        $effectiveMax = in_array($orientation, [6, 8], true) ? max($width, $height) : max($width, $height);

        if ($effectiveMax <= $maxSide && $before < $minBytes) {
            return null; // já é pequena
        }

        $previousLimit = self::ensureMemory($width * $height);
        if ($previousLimit === false) {
            return null;
        }

        $tmp = $path.'.downscale.tmp';
        $src = $dst = null;
        try {
            $src = match ($type) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
                IMAGETYPE_PNG => @imagecreatefrompng($path),
                IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            };
            if (! $src) {
                return null;
            }

            if ($angle !== 0) {
                $rotated = imagerotate($src, $angle, 0);
                if (! $rotated) {
                    return null;
                }
                $src = $rotated;
            }

            $w = imagesx($src);
            $h = imagesy($src);
            $scale = min(1.0, $maxSide / max($w, $h));
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));

            $dst = imagecreatetruecolor($nw, $nh);
            if ($type !== IMAGETYPE_JPEG) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

            $written = match ($type) {
                IMAGETYPE_JPEG => imageinterlace($dst, true) !== null && imagejpeg($dst, $tmp, $quality),
                IMAGETYPE_PNG => imagepng($dst, $tmp, 6),
                IMAGETYPE_WEBP => imagewebp($dst, $tmp, $quality),
            };
            $after = $written ? @filesize($tmp) : false;
            if ($after === false || $after >= $before) {
                return null; // não ficou menor: mantém o original
            }

            @chmod($tmp, fileperms($path) & 0777);
            if (! rename($tmp, $path)) {
                return null;
            }

            return ['from_bytes' => $before, 'to_bytes' => $after, 'from' => "{$width}x{$height}", 'to' => "{$nw}x{$nh}"];
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
            if ($previousLimit !== null) {
                @ini_set('memory_limit', $previousLimit);
            }
        }
    }

    /** Orientação EXIF (1-8); 1 quando não há EXIF ou a extensão não existe. */
    private static function exifOrientation(string $path): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }
        $exif = @exif_read_data($path);

        return is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;
    }

    /**
     * O GD decodifica a ~4 bytes/pixel e mantém uma cópia ao redimensionar. Sobe o memory_limit só
     * durante a operação, se preciso. Devolve o limite anterior (para restaurar), null se nada mudou,
     * ou false se não dá para garantir memória (aí não se mexe na foto).
     */
    private static function ensureMemory(int $pixels): string|false|null
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return null;
        }
        $needed = (int) ($pixels * 4 * 2.2) + 32 * 1024 * 1024;
        $limitBytes = self::toBytes((string) $limit);
        $free = $limitBytes - memory_get_usage(true);
        if ($free >= $needed) {
            return null;
        }
        $wanted = memory_get_usage(true) + $needed;
        if (@ini_set('memory_limit', (string) $wanted) === false || self::toBytes((string) ini_get('memory_limit')) < $wanted) {
            @ini_set('memory_limit', $limit);

            return false;
        }

        return (string) $limit;
    }

    private static function toBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
