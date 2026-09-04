<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Filament\Resources\WarehouseResource\RelationManagers;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WarehouseResource extends BaseResource
{
    protected static ?string $model = Warehouse::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?int $navigationSort = 10;

    protected static ?string $label = 'Almoxarifado';

    protected static ?string $pluralLabel = 'Almoxarifados';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informações Básicas')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome do Almoxarifado')
                        ->placeholder('Galpão Principal - São Paulo')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('code')
                        ->label('Código')
                        ->placeholder('ALM-01')
                        ->maxLength(50),
                ])
                ->columns(2),

            Forms\Components\Section::make('Localização')
                ->schema([
                    Forms\Components\TextInput::make('address')
                        ->label('Endereço')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('city')
                        ->label('Cidade')
                        ->maxLength(100),

                    Forms\Components\TextInput::make('state')
                        ->label('Estado (UF)')
                        ->maxLength(2),
                ])
                ->columns(3),

            Forms\Components\Section::make('Responsável e Status')
                ->schema([
                    Forms\Components\SelectRelation::make('manager_id')
                        ->relationship('manager', 'name')
                        ->label('Gerente do Almoxarifado')
                        ->searchable()
                        ->preload(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Ativo')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Almoxarifado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('Cidade')
                    ->sortable(),

                Tables\Columns\TextColumn::make('manager.name')
                    ->label('Gerente')
                    ->sortable(),

                Tables\Columns\TextColumn::make('stocks_count')
                    ->label('Itens em Estoque')
                    ->counts('stocks')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_stock_value')
                    ->label('Valor Total em Estoque')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Todos')
                    ->trueLabel('Ativos')
                    ->falseLabel('Inativos'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('stocks')
                    ->label('Estoque')
                    ->icon('heroicon-o-cube-transparent')
                    ->color('info')
                    ->url(fn (Warehouse $record) => WarehouseStockResource::getUrl('index', ['warehouses' => $record->id])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StocksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'view' => Pages\ViewWarehouse::route('/{record}'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
