<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockMovementResource extends BaseResource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?int $navigationSort = 14;

    protected static ?string $label = 'Movimentação de Estoque';

    protected static ?string $pluralLabel = 'Histórico de Movimentações (Kardex)';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dados da Movimentação')
                ->schema([
                    Forms\Components\TextInput::make('movement_type')
                        ->label('Tipo de Movimentação')
                        ->readOnly(),

                    Forms\Components\TextInput::make('reference_document')
                        ->label('Documento de Referência')
                        ->readOnly(),

                    Forms\Components\TextInput::make('part.sku')
                        ->label('SKU da Peça')
                        ->readOnly(),

                    Forms\Components\TextInput::make('part.name')
                        ->label('Peça')
                        ->readOnly(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Quantidade e Saldo')
                ->schema([
                    Forms\Components\TextInput::make('quantity')
                        ->label('Quantidade Movimentada')
                        ->readOnly()
                        ->numeric(decimals: 2),

                    Forms\Components\TextInput::make('balance_before')
                        ->label('Saldo Anterior')
                        ->readOnly()
                        ->numeric(decimals: 2),

                    Forms\Components\TextInput::make('balance_after')
                        ->label('Novo Saldo')
                        ->readOnly()
                        ->numeric(decimals: 2),
                ])
                ->columns(3),

            Forms\Components\Section::make('Custos')
                ->schema([
                    Forms\Components\TextInput::make('unit_cost')
                        ->label('Custo Unitário (R$)')
                        ->readOnly()
                        ->money('BRL'),

                    Forms\Components\TextInput::make('total_cost')
                        ->label('Custo Total (R$)')
                        ->readOnly()
                        ->money('BRL'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Detalhes')
                ->schema([
                    Forms\Components\TextInput::make('warehouse.name')
                        ->label('Almoxarifado')
                        ->readOnly(),

                    Forms\Components\TextInput::make('createdBy.name')
                        ->label('Registrado por')
                        ->readOnly(),

                    Forms\Components\TextInput::make('created_at')
                        ->label('Data/Hora')
                        ->readOnly(),

                    Forms\Components\Textarea::make('notes')
                        ->label('Observações')
                        ->readOnly()
                        ->rows(3),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('movement_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'entry_purchase' => 'success',
                        'entry_adjustment' => 'info',
                        'entry_return' => 'info',
                        'exit_work_order' => 'warning',
                        'exit_adjustment' => 'danger',
                        'exit_loss' => 'danger',
                        'transfer_out' => 'secondary',
                        'transfer_in' => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'entry_purchase' => 'Compra',
                        'entry_adjustment' => 'Ajuste (Entrada)',
                        'entry_return' => 'Devolução',
                        'exit_work_order' => 'OS/Trabalho',
                        'exit_adjustment' => 'Ajuste (Saída)',
                        'exit_loss' => 'Perda/Dano',
                        'transfer_out' => 'Transfer. (Saída)',
                        'transfer_in' => 'Transfer. (Entrada)',
                    })
                    ->sortable(),

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
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd.')
                    ->numeric(decimals: 2)
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('balance_before')
                    ->label('Saldo Anterior')
                    ->numeric(decimals: 2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Novo Saldo')
                    ->numeric(decimals: 2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Custo Unit. (R$)')
                    ->money('BRL')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Custo Total (R$)')
                    ->money('BRL')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reference_document')
                    ->label('Referência')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Registrado por')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Observações')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Almoxarifado')
                    ->relationship('warehouse', 'name'),

                Tables\Filters\SelectFilter::make('part_id')
                    ->label('Peça')
                    ->relationship('part', 'name'),

                Tables\Filters\SelectFilter::make('movement_type')
                    ->label('Tipo de Movimentação')
                    ->options([
                        'entry_purchase' => 'Compra',
                        'entry_adjustment' => 'Ajuste (Entrada)',
                        'entry_return' => 'Devolução',
                        'exit_work_order' => 'OS/Trabalho',
                        'exit_adjustment' => 'Ajuste (Saída)',
                        'exit_loss' => 'Perda/Dano',
                        'transfer_out' => 'Transfer. (Saída)',
                        'transfer_in' => 'Transfer. (Entrada)',
                    ]),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('De'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Até'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn ($q) => $q->whereDate('created_at', '>=', $data['created_from']),
                            )
                            ->when(
                                $data['created_until'],
                                fn ($q) => $q->whereDate('created_at', '<=', $data['created_until']),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
            'view' => Pages\ViewStockMovement::route('/{record}'),
        ];
    }
}
