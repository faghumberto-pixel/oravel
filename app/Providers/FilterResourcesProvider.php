<?php

namespace App\Providers;

use App\Services\FeatureDiscoveryService;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class FilterResourcesProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->filterNavigation();
    }

    private function filterNavigation(): void
    {
        $user = Auth::user();
        if (!$user || $user->isSuperAdmin()) {
            return;
        }

        $tenant = $user->tenant;
        if (!$tenant) {
            return;
        }

        $featureMapping = FeatureDiscoveryService::getAllFeatureMapping();

        try {
            $panel = Filament::getPanel('admin');
            if (!$panel) {
                return;
            }

            $navigation = $panel->getNavigation();

            foreach ($navigation as $item) {
                if ($item instanceof NavigationGroup) {
                    $items = $item->getItems();
                    
                    foreach ($items as $navItem) {
                        if ($navItem instanceof NavigationItem) {
                            $resourceClass = $navItem->getResourceClass();
                            
                            if ($resourceClass) {
                                $featureSlug = $featureMapping[$resourceClass] ?? null;
                                
                                if ($featureSlug && !$tenant->hasFeature($featureSlug)) {
                                    $navItem->visible(false);
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Falha silenciosa
        }
    }
}
