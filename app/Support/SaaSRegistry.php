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

        foreach (static::modelDirectories() as $namespace => $dir) {
            foreach (glob($dir.'/*.php') as $file) {
                $class = $namespace.'\\'.basename($file, '.php');

                if (! class_exists($class)) {
                    continue;
                }

                if (! method_exists($class, 'isSaaSModule') || ! $class::isSaaSModule()) {
                    continue;
                }

                $modules[] = [
                    'model' => $class,
                    'slug' => $class::saasPermissionSlug(),
                    'feature' => $class::saasFeatureKey(),
                    'label' => $class::saasModuleLabel(),
                ];
            }
        }

        usort($modules, fn ($a, $b) => strcmp($a['label'] ?? '', $b['label'] ?? ''));

        return static::$cache = $modules;
    }

    /**
     * Bug real achado 2026-09-24: o scan cobria só app/Models/*.php
     * (namespace App\Models), então todo model dentro de
     * app/Domain/{Dominio}/Models/ (ex: App\Domain\Fleet\Models --
     * ContractMeasurement, RentalHourFranchise, RentalOverageCharge, as
     * *Specification) ficava INVISÍVEL pro registro inteiro -- mesmo
     * declarando HasSaaSMetadata e saasFeatureKey corretamente. Isso não
     * só escondia esses módulos do checklist de Contratos/Planos (sem
     * chave, não tinha como marcar/desmarcar), como fazia
     * AbstractPolicy::getFeatureKeyFromModel() (via SaaSRegistry::forModel())
     * retornar null pra eles -- e um featureKey null pula o gate de plano
     * por inteiro, liberando o módulo pra QUALQUER contrato,
     * independentemente do que foi marcado. Descoberta agora inclui
     * qualquer app/Domain/{Dominio}/Models/ existente, sem precisar
     * hardcodar "Fleet" -- novo domínio adicionado no futuro entra sozinho.
     *
     * @return array<string, string> namespace => diretório absoluto
     */
    protected static function modelDirectories(): array
    {
        $directories = ['App\\Models' => app_path('Models')];

        foreach (glob(app_path('Domain').'/*', GLOB_ONLYDIR) ?: [] as $domainDir) {
            $modelsDir = $domainDir.'/Models';

            if (is_dir($modelsDir)) {
                $directories['App\\Domain\\'.basename($domainDir).'\\Models'] = $modelsDir;
            }
        }

        return $directories;
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
        static::$resourceGroupCache = null;
    }

    protected static ?array $resourceGroupCache = null;

    /**
     * Mapa Model::class => $navigationGroup do Resource correspondente,
     * construído varrendo app/Filament/Resources/*.php uma única vez
     * (mesmo padrão de descoberta já usado por
     * App\Console\Commands\AuditSaaSMetadata::modelsWithAdminResource()).
     * Cacheado em request, igual modules().
     *
     * @return array<string, string>
     */
    protected static function resourceNavigationGroups(): array
    {
        if (static::$resourceGroupCache !== null) {
            return static::$resourceGroupCache;
        }

        $groups = [];

        foreach (glob(app_path('Filament/Resources').'/*.php') as $file) {
            $className = 'App\\Filament\\Resources\\'.basename($file, '.php');

            if (! class_exists($className) || ! property_exists($className, 'model')) {
                continue;
            }

            $reflection = new \ReflectionClass($className);

            $modelProperty = $reflection->getProperty('model');
            $modelProperty->setAccessible(true);
            $modelClass = $modelProperty->getValue();

            if (! $modelClass) {
                continue;
            }

            $group = null;
            if ($reflection->hasProperty('navigationGroup')) {
                $groupProperty = $reflection->getProperty('navigationGroup');
                $groupProperty->setAccessible(true);
                $group = $groupProperty->getValue();
            }

            $groups[$modelClass] = $group ?: 'Outros';
        }

        return static::$resourceGroupCache = $groups;
    }

    /**
     * Módulos do SaaSRegistry agrupados pelo mesmo $navigationGroup do menu
     * lateral (pedido do usuário 2026-09-23: "as tabelas assim como os
     * menus estejam agrupadas como menus e submenus" -- ex: Manutenção
     * agrupando OS, Avarias etc). Um módulo sem Resource correspondente
     * (ex: 'modulo_dashboard', que não tem Model por trás) cai em 'Outros'.
     *
     * @return array<string, array<int, array{model:string, slug:string, feature:?string, label:?string}>>
     */
    public static function modulesGroupedByNavigation(): array
    {
        $resourceGroups = static::resourceNavigationGroups();
        $grouped = [];

        foreach (static::modules() as $module) {
            $group = $resourceGroups[$module['model']] ?? 'Outros';
            $grouped[$group][] = $module;
        }

        ksort($grouped);

        return $grouped;
    }
}
