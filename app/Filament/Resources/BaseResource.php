<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

abstract class BaseResource extends Resource
{ 
    protected static bool $shouldRegisterNavigation = false;
    // Esta classe não deve ser registrada como um recurso, por isso a tornamos abstrata.
    // Ela serve apenas para herança.
}