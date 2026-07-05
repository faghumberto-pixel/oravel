<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Resources\AssetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

#[BelongsToFeature('assets')]
class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Novo Ativo'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AssetResource\Widgets\AssetStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
