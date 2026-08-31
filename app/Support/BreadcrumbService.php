<?php

namespace App\Support;

use Filament\Facades\Filament;

class BreadcrumbService
{
    public static function getModuleAndPageTitle(): array
    {
        try {
            $routeName = request()->route()?->getName();

            if (!$routeName) {
                return ['module' => null, 'title' => null];
            }

            [$panel, $type, $slug, $action] = self::parseRouteName($routeName);

            if (!$panel) {
                return ['module' => null, 'title' => null];
            }

            if ($type === 'resources') {
                return self::getResourceBreadcrumb($slug);
            } elseif ($type === 'pages') {
                return self::getPageBreadcrumb($slug);
            }

            return ['module' => null, 'title' => null];
        } catch (\Throwable $e) {
            return ['module' => null, 'title' => null];
        }
    }

    private static function parseRouteName(string $routeName): array
    {
        $parts = explode('.', $routeName);

        if (count($parts) < 4 || $parts[0] !== 'filament') {
            return [null, null, null, null];
        }

        $panel = $parts[1];
        $type = $parts[2];
        $slug = $parts[3] ?? null;
        $action = $parts[4] ?? null;

        return [$panel, $type, $slug, $action];
    }

    private static function getResourceBreadcrumb(string $slug): array
    {
        $resources = Filament::getResources();

        foreach ($resources as $resourceClass) {
            $resourceSlug = $resourceClass::getSlug();

            if ($resourceSlug === $slug) {
                $module = $resourceClass::getNavigationGroup();
                $title = $resourceClass::getPluralModelLabel();

                return [
                    'module' => $module,
                    'title' => $title,
                ];
            }
        }

        return ['module' => null, 'title' => null];
    }

    private static function getPageBreadcrumb(string $slug): array
    {
        $pages = Filament::getPages();

        foreach ($pages as $pageClass) {
            $pageSlug = $pageClass::getSlug();

            if ($pageSlug === $slug) {
                $module = $pageClass::getNavigationGroup();

                $title = self::getPageTitle($pageClass);

                return [
                    'module' => $module,
                    'title' => $title,
                ];
            }
        }

        return ['module' => null, 'title' => null];
    }

    private static function getPageTitle(string $pageClass): ?string
    {
        $reflection = new \ReflectionClass($pageClass);

        if ($reflection->hasProperty('title')) {
            $property = $reflection->getProperty('title');

            if ($property->isStatic()) {
                return $property->getValue() ?? null;
            }
        }

        return null;
    }
}
