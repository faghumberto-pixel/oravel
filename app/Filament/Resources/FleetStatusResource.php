<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FleetStatusResource\Pages;
use App\Models\FleetStatus;
use App\Support\Tenancy;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FleetStatusResource extends Resource
{
    protected static ?string $model = FleetStatus::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão Comercial';

    protected static ?string $navigationLabel = 'Status da Frota';

    public static function getEloquentQuery(): Builder
    {
        $tenant = Tenancy::current();

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
