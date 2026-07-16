<?php

namespace App\Filament\Resources\MaterialResource\RelationManagers;

use App\Models\InternalUnit;
use App\Models\Material;
use App\Services\MaterialStockService;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Estoque do Material por filial -- substitui o campo unico current_stock
 * editavel direto (agora so leitura, ver MaterialResource::form()). Criar
 * uma linha nova e ajustar quantidade passam por MaterialStockService
 * (nao Eloquent direto), pra sempre gerar um MaterialStockMovement de
 * verdade -- so os limiares (minimo/maximo) sao editaveis inline sem
 * ledger, ja que nao mexem em saldo.
 */
class LocationStocksRelationManager extends RelationManager
{
    protected static string $relationship = 'locationStocks';

    protected static ?string $title = 'Estoque por Filial';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('internal_unit_id')
                ->label('Filial')
                ->relationship('internalUnit', 'name', fn ($query) => $query->where('tenant_id', Tenancy::current()?->id))
                ->required()
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('current_quantity')
                ->label('Quantidade Inicial')
                ->numeric()
                ->default(0)
                ->required(),
            Forms\Components\TextInput::make('minimum_threshold')
                ->label('Estoque Mínimo')
                ->numeric()
                ->default(fn () => $this->getOwnerRecord()->min_stock ?? 0)
                ->required(),
            Forms\Components\TextInput::make('maximum_threshold')
                ->label('Estoque Máximo')
                ->numeric()
                ->default(fn () => $this->getOwnerRecord()->max_stock ?: null),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('internalUnit.name')
            ->columns([
                Tables\Columns\TextColumn::make('internalUnit.name')
                    ->label('Filial')
                    ->searchable(),
                Tables\Columns\TextColumn::make('current_quantity')
                    ->label('Quantidade Atual')
                    ->numeric()
                    ->color(fn ($record) => $record->isLowStock() ? 'danger' : 'success')
                    ->alignEnd(),
                Tables\Columns\TextInputColumn::make('minimum_threshold')
                    ->label('Mínimo')
                    ->type('number')
                    ->rules(['required', 'integer', 'min:0']),
                Tables\Columns\TextInputColumn::make('maximum_threshold')
                    ->label('Máximo')
                    ->type('number')
                    ->rules(['nullable', 'integer', 'min:0']),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data) {
                        /** @var Material $material */
                        $material = $this->getOwnerRecord();
                        $unit = InternalUnit::find($data['internal_unit_id']);

                        $stock = app(MaterialStockService::class)->adjust(
                            $material,
                            $unit,
                            (float) $data['current_quantity'],
                            auth()->id(),
                        );

                        $stock->update([
                            'minimum_threshold' => $data['minimum_threshold'],
                            'maximum_threshold' => $data['maximum_threshold'] ?: null,
                        ]);

                        return $stock;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('ajustar')
                    ->label('Ajustar Quantidade')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->form([
                        Forms\Components\TextInput::make('nova_quantidade')
                            ->label('Nova Quantidade Contada')
                            ->numeric()
                            ->required(),
                    ])
                    ->action(function (array $data, $record) {
                        app(MaterialStockService::class)->adjust(
                            $record->material,
                            $record->internalUnit,
                            (float) $data['nova_quantidade'],
                            auth()->id(),
                        );

                        Notification::make()->title('Estoque ajustado')->success()->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => (int) $record->current_quantity === 0),
            ])
            ->bulkActions([]);
    }
}
