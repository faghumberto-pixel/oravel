<?php

namespace App\Filament\Resources\BranchResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\BranchResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('branches')]
class CreateBranch extends CreateRecord
{
    protected static string $resource = BranchResource::class;
}
