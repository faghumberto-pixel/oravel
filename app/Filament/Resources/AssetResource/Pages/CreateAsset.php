<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\AssetResource;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('assets')]
class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}