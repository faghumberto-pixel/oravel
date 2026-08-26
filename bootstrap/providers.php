<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\CentralPanelProvider;
use App\Providers\Filament\ClientPanelProvider;
use App\Providers\TenantServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    CentralPanelProvider::class,
    ClientPanelProvider::class,
    TenantServiceProvider::class,
];
