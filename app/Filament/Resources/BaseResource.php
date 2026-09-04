<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

abstract class BaseResource extends Resource
{
    // Padrão: registrar na navegação (pode ser sobrescrito por filhas que herdam de BaseResource)
    protected static bool $shouldRegisterNavigation = true;
}
