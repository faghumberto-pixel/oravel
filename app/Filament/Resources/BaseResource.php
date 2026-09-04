<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

abstract class BaseResource extends Resource
{
    // Resources herdam a definição de shouldRegisterNavigation de cada classe filha
    // (não forçar false aqui permite que Resources individuais a sobrescrevam)
}
