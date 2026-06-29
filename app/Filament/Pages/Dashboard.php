<?php

namespace App\Filament\Pages;

use App\Filament\Attributes\BelongsToFeature;
use Filament\Pages\Dashboard as BaseDashboard;

#[BelongsToFeature('users')]
class Dashboard extends BaseDashboard
{
}
