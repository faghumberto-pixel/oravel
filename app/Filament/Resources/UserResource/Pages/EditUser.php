<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

#[BelongsToFeature('users')]
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
