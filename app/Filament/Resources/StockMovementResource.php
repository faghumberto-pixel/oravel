<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\MaterialStockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class StockMovementResource extends BaseResource
{
    protected static ?string $model = MaterialStockMovement::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationParentItem = 'Almoxarifado';

    protected static ?int $navigationSort = 6;

    protected static ?string $label = 'Histórico de Movimentação';

    protected static ?string $pluralLabel = 'Histórico de Movimentações';

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
                    Forms\Components\TextInput::make('type')
                        ->label('Tipo')
                        ->readOnly(),

                    Forms\Components\TextInput::make('material.name')
                        ->label('Material')
                        ->readOnly(),

                    Forms\Components\TextInput::make('material.sku')
                        ->label('SKU')
                        ->readOnly(),
                ])
                ->columns(3),

            Forms\Components\Section::make('Quantidade')
                ->schema([
                    Forms\Components\TextInput::make('quantity')
                        ->label('Quantidade Movimentada')
                        ->readOnly(),

                    Forms\Components\TextInput::make('balance_after')
                        ->label('Saldo após movimento')
                        ->readOnly(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Referência')
                ->schema([
                    Forms\Components\TextInput::make('document_reference')
                        ->label('Documento de Referência')
                        ->readOnly(),

                    Forms\Components\TextInput::make('reason')
                        ->label('Motivo')
                        ->readOnly(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Detalhes')
                ->schema([
                    Forms\Components\TextInput::make('created_at')
                        ->label('Data/Hora')
                        ->readOnly(),

                    Forms\Components\TextInput::make('created_by_user.name')
                        ->label('Registrado por')
                        ->readOnly(),
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

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'entrada' => 'success',
                        'saída' => 'warning',
                        'ajuste' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('material.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('material.name')
                    ->label('Material')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd.')
                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.'))
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Saldo')
                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('document_reference')
                    ->label('Referência')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_by_user.name')
                    ->label('Registrado por')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'entrada' => 'Entrada',
                        'saída' => 'Saída',
                        'ajuste' => 'Ajuste',
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
