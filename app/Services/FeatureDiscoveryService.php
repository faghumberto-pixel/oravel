<?php
namespace App\Services;
use App\Filament\Attributes\BelongsToFeature;
use Illuminate\Support\Facades\File;
use ReflectionClass;
class FeatureDiscoveryService {
    private static array $searchPaths = ['app/Filament/Resources', 'app/Filament/Pages'];
    public static function getAllFeatureMapping(): array {
        $mapping = [];
        foreach (self::$searchPaths as $path) {
            if (!is_dir($path)) continue;
            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') continue;
                $className = self::getClassNameFromFile($file->getPathname());
                if (!$className) continue;
                try {
                    $reflection = new ReflectionClass($className);
                    $attributes = $reflection->getAttributes(BelongsToFeature::class);
                    if (!empty($attributes)) {
                        $attribute = $attributes[0]->newInstance();
                        $mapping[$className] = $attribute->featureSlug;
                    }
                } catch (\Exception $e) { continue; }
            }
        }
        return $mapping;
    }
    public static function getFeatureMapping(): array {
        $mapping = self::getAllFeatureMapping();
        $byFeature = [];
        foreach ($mapping as $className => $featureSlug) {
            if (!isset($byFeature[$featureSlug])) $byFeature[$featureSlug] = [];
            $byFeature[$featureSlug][] = $className;
        }
        return $byFeature;
    }
    private static function getClassNameFromFile(string $filePath): ?string {
        $relativePath = str_replace(base_path(), '', $filePath);
        $relativePath = ltrim($relativePath, '/');
        $namespace = str_replace(['/', '.php'], ['\\', ''], $relativePath);
        if (!str_starts_with($namespace, 'app')) return null;
        $namespace = str_replace('app', 'App', $namespace, 1);
        return str_contains($namespace, '\\') ? $namespace : 'App\\' . str_replace('/', '\\', str_replace('app/', '', $relativePath, 1));
    }
}
