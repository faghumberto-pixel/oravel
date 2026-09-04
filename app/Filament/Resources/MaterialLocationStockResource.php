<?php

namespace App\Filament\Resources;

use App\Filament\Pages\Almoxarifado;
use App\Models\MaterialLocationStock;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaterialLocationStockResource extends BaseResource
{
    protected static ?string $model = MaterialLocationStock::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationParentItem = 'Almoxarifado';

    protected static ?string $label = 'Saldo por Filial';

    protected static ?string $pluralLabel = 'Saldos em Estoque';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações do Saldo')
                    ->schema([
                        Forms\Components\Select::make('material_id')
                            ->label('Material')
                            ->relationship('material', 'name', modifyQueryUsing: function (Builder $query) {
                                return $query->where('tenant_id', Tenancy::current()?->id);
                            })
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('internal_unit_id')
                            ->label('Filial')
                            ->relationship('internalUnit', 'name', modifyQueryUsing: function (Builder $query) {
                                return $query->where('tenant_id', Tenancy::current()?->id);
                            })
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('current_quantity')
                            ->label('Quantidade Atual')
                            ->numeric()
                            ->required()
                            ->step(0.01),

                        Forms\Components\TextInput::make('minimum_threshold')
                            ->label('Estoque Mínimo')
                            ->numeric()
                            ->required()
                            ->step(0.01),

                        Forms\Components\TextInput::make('maximum_threshold')
                            ->label('Estoque Máximo')
                            ->numeric()
                            ->required()
                            ->step(0.01),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('material.sku')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('material.name')
                    ->label('Material')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('internalUnit.name')
                    ->label('Filial')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_quantity')
                    ->label('Quantidade')
                    ->numeric(decimalPlaces: 2)
                    ->color(fn($record) => $record->isLowStock() ? 'danger' : 'success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('minimum_threshold')
                    ->label('Mínimo')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                Tables\Columns\TextColumn::make('maximum_threshold')
                    ->label('Máximo')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('material')
                    ->relationship('material', 'name')
                    ->label('Material'),

                Tables\Filters\SelectFilter::make('internalUnit')
                    ->relationship('internalUnit', 'name')
                    ->label('Filial'),

                Tables\Filters\Filter::make('baixo_estoque')
                    ->label('Abaixo do Mínimo')
                    ->query(fn($query) => $query->whereColumn('current_quantity', '<=', 'minimum_threshold'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\MaterialLocationStockResource\Pages\ListMaterialLocationStocks::route('/'),
            'create' => \App\Filament\Resources\MaterialLocationStockResource\Pages\CreateMaterialLocationStock::route('/create'),
            'edit' => \App\Filament\Resources\MaterialLocationStockResource\Pages\EditMaterialLocationStock::route('/{record}/edit'),
        ];
    }
}
