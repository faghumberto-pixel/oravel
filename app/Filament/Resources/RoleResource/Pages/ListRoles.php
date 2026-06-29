<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

#[BelongsToFeature('roles')]
class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}