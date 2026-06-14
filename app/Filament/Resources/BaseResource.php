<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

abstract class BaseResource extends Resource
{

    protected static bool $shouldRegisterNavigation = false;
}
