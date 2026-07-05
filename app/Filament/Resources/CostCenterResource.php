<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\CostCenterResource\Pages;
use App\Models\CostCenter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

#[BelongsToFeature('cost_centers')]
class CostCenterResource extends Resource
{
    protected static ?string $model = CostCenter::class;

    // 🚀 OBRIGATÓRIO: Isola o centro de custo pela empresa logada
    protected static ?string $tenantRelationshipName = 'costCenters';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Configurações';
    protected static ?string $navigationLabel = 'Centros de Custo';
    protected static ?string $modelLabel = 'Centro de Custo';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dados do Centro de Custo')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome do Centro de Custo')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('code')
                        ->label('Código Contábil')
                        ->maxLength(50),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('code')->label('Código')->searchable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCostCenters::route('/'),
            'create' => Pages\CreateCostCenter::route('/create'),
            'edit' => Pages\EditCostCenter::route('/{record}/edit'),
        ];
    }
}