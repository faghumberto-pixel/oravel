<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseStockResource\Pages;
use App\Models\WarehouseStock;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WarehouseStockResource extends BaseResource
{
    protected static ?string $model = WarehouseStock::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?int $navigationSort = 12;

    protected static ?string $label = 'Saldo em Estoque';

    protected static ?string $pluralLabel = 'Saldos em Estoque';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Saldo de Estoque')
                ->schema([
                    Forms\Components\SelectRelation::make('warehouse_id')
                        ->relationship('warehouse', 'name')
                        ->label('Almoxarifado')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->disabled(),

                    Forms\Components\SelectRelation::make('part_id')
                        ->relationship('part', 'name')
                        ->label('Peça/Insumo')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->disabled(),

                    Forms\Components\TextInput::make('current_quantity')
                        ->label('Quantidade Atual')
                        ->numeric()
                        ->readOnly(),

                    Forms\Components\TextInput::make('reserved_quantity')
                        ->label('Quantidade Reservada')
                        ->numeric()
                        ->readOnly(),

                    Forms\Components\TextInput::make('available_quantity')
                        ->label('Quantidade Disponível')
                        ->numeric()
                        ->readOnly()
                        ->helperText('= Atual - Reservada'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Análise de Estoque')
                ->schema([
                    Forms\Components\TextInput::make('part.minimum_stock')
                        ->label('Mínimo Recomendado')
                        ->numeric()
                        ->readOnly(),

                    Forms\Components\TextInput::make('part.maximum_stock')
                        ->label('Máximo Permitido')
                        ->numeric()
                        ->readOnly(),

                    Forms\Components\TextInput::make('stock_percentage')
                        ->label('Percentual do Máximo (%)')
                        ->numeric()
                        ->readOnly()
                        ->suffix('%'),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Almoxarifado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('part.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('part.name')
                    ->label('Peça')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('part.unit_of_measure')
                    ->label('Unidade')
                    ->badge(),

                Tables\Columns\TextColumn::make('current_quantity')
                    ->label('Atual')
                    ->formatStateUsing(fn ($state) => number_format($state ?? 0, 2, ",", "."))
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('reserved_quantity')
                    ->label('Reservado')
                    ->formatStateUsing(fn ($state) => number_format($state ?? 0, 2, ",", "."))
                    ->sortable()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('available_quantity')
                    ->label('Disponível')
                    ->formatStateUsing(fn ($state) => number_format($state ?? 0, 2, ",", "."))
                    ->sortable()
                    ->weight('bold')
                    ->color(fn (WarehouseStock $record) => match (true) {
                        $record->is_critical => 'danger',
                        default => 'success',
                    }),

                Tables\Columns\TextColumn::make('is_critical')
                    ->label('Status')
                    ->badge()
                    ->color('danger')
                    ->state(fn (WarehouseStock $record) => $record->is_critical ? 'CRÍTICO' : 'OK')
                    ->visible(fn (WarehouseStock $record) => $record->is_critical),

                Tables\Columns\TextColumn::make('part.cost_price')
                    ->label('Custo Unit. (R$)')
                    ->money('BRL')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Almoxarifado')
                    ->relationship('warehouse', 'name'),

                Tables\Filters\SelectFilter::make('part.part_category_id')
                    ->label('Categoria de Peça')
                    ->relationship('part.category', 'name'),

                Tables\Filters\TernaryFilter::make('is_critical')
                    ->label('Status Crítico')
                    ->placeholder('Todos')
                    ->trueLabel('Críticos')
                    ->falseLabel('Normais'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('movements')
                    ->label('Movimentações')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->url(fn (WarehouseStock $record) => StockMovementResource::getUrl('index', [
                        'warehouse_id' => $record->warehouse_id,
                        'part_id' => $record->part_id,
                    ])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ])
            ->defaultSort('warehouse_id', 'asc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouseStocks::route('/'),
            'view' => Pages\ViewWarehouseStock::route('/{record}'),
        ];
    }
}
