<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Historico de entrada/saida de estoque -- so'-leitura de proposito,
 * mesmo padrao de EquipmentMovementResource: cada linha nasce sozinha
 * (consumo em O.S. via MaterialConsumptionService, recebimento de
 * compra, ou ajuste de inventario via MaterialStockTake::finalize()),
 * nunca criada/editada por aqui.
 */
#[BelongsToFeature('material_stock_movements')]
class StockMovementResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = StockMovement::class;

    protected static ?string $modelLabel = 'Movimentação de Estoque';

    protected static ?string $pluralModelLabel = 'Histórico de Estoque';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('material_id')
                ->label('Material')
                ->relationship('material', 'name')
                ->disabled(),
            Forms\Components\TextInput::make('type')->label('Tipo')->disabled(),
            Forms\Components\TextInput::make('quantity')->label('Quantidade')->disabled(),
            Forms\Components\TextInput::make('balance_after')->label('Saldo Após')->disabled(),
            Forms\Components\Select::make('created_by_user_id')
                ->label('Registrado por')
                ->relationship('createdBy', 'name')
                ->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('material.sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('material.name')
                    ->label('Material')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tipo')
                    ->colors([
                        'success' => StockMovement::TYPE_ENTRADA_COMPRA,
                        'danger' => StockMovement::TYPE_SAIDA_CONSUMO,
                        'warning' => StockMovement::TYPE_AJUSTE_MANUAL,
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        StockMovement::TYPE_ENTRADA_COMPRA => 'Entrada (Compra)',
                        StockMovement::TYPE_SAIDA_CONSUMO => 'Saída (Consumo)',
                        StockMovement::TYPE_AJUSTE_MANUAL => 'Ajuste (Inventário)',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantidade')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Saldo Após')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Registrado por')
                    ->placeholder('Sistema'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        StockMovement::TYPE_ENTRADA_COMPRA => 'Entrada (Compra)',
                        StockMovement::TYPE_SAIDA_CONSUMO => 'Saída (Consumo)',
                        StockMovement::TYPE_AJUSTE_MANUAL => 'Ajuste (Inventário)',
                    ]),
                Tables\Filters\SelectFilter::make('material_id')
                    ->label('Material')
                    ->relationship('material', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
            'view' => Pages\ViewStockMovement::route('/{record}'),
        ];
    }
}
