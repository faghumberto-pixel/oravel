<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\FleetStatusResource\Pages;
use App\Models\FleetStatus;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

#[BelongsToFeature('fleet')]
class FleetStatusResource extends Resource
{
    protected static ?string $model = FleetStatus::class;


    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'LOGÍSTICA';

    protected static ?string $navigationLabel = 'Status da Frota';

    public static function getEloquentQuery(): Builder
    {
        $tenant = \App\Support\Tenancy::current();
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Campos do formulário configurados futuramente
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Colunas da tabela configuradas futuramente
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFleetStatuses::route('/'),
            'create' => Pages\CreateFleetStatus::route('/create'),
            'edit' => Pages\EditFleetStatus::route('/{record}/edit'),
        ];
    }
}
