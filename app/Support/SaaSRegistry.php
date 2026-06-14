<?php

namespace App\Support;

/**
 * Registro Central de Módulos SaaS.
 *
 * Descobre automaticamente todos os Models que usam a trait HasSaaSMetadata
 * e expõe seus metadados (slug de permissão, feature de plano, label, classe).
 *
 * Esta é a ÚNICA fonte que Policy, RoleResource e Navigation devem consultar.
 * Criar um Model novo com a trait = ele aparece aqui sozinho, sem editar nada.
 */
class SaaSRegistry
{
    protected static ?array $cache = null;

    /**
     * @return array<int, array{model:string, slug:string, feature:?string, label:?string}>
     */
    public static function modules(): array
    {
        if (static::$cache !== null) {
            return static::$cache;
        }

        $modules = [];

        foreach (glob(app_path('Models') . '/*.php') as $file) {
            $class = 'App\\Models\\' . basename($file, '.php');

            if (!class_exists($class)) {
                continue;
            }

            if (!method_exists($class, 'isSaaSModule') || !$class::isSaaSModule()) {
                continue;
            }

            $modules[] = [
                'model'   => $class,
                'slug'    => $class::saasPermissionSlug(),
                'feature' => $class::saasFeatureKey(),
                'label'   => $class::saasModuleLabel(),
            ];
        }

        usort($modules, fn ($a, $b) => strcmp($a['label'] ?? '', $b['label'] ?? ''));

        return static::$cache = $modules;
    }

    /**
     * @return array<int, string>
     */
    public static function permissionSlugs(): array
    {
        return array_values(array_filter(array_map(
            fn ($m) => $m['slug'],
            static::modules()
        )));
    }

    /**
     * @return array{model:string, slug:string, feature:?string, label:?string}|null
     */
    public static function forModel(string $modelClass): ?array
    {
        foreach (static::modules() as $m) {
            if ($m['model'] === $modelClass) {
                return $m;
            }
        }
        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function labelsBySlug(): array
    {
        $map = [];
        foreach (static::modules() as $m) {
            if ($m['slug']) {
                $map[$m['slug']] = $m['label'] ?? $m['slug'];
            }
        }
        return $map;
    }

    public static function flush(): void
    {
        static::$cache = null;
    }
}
