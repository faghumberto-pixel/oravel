<?php

namespace App\Filament\Resources\StockLocationResource\RelationManagers;

use App\Models\AggregateItemType;
use App\Models\Material;
use App\Models\Part;
use App\Models\StockPlacement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** O que está neste endereço e quanto: base da folha de contagem física. */
class PlacementsRelationManager extends RelationManager
{
    protected static string $relationship = 'placements';

    protected static ?string $title = 'Itens neste endereço';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('item_type')->label('Tipo')->options(StockPlacement::TIPOS)->live()->required(),
            Forms\Components\Select::make('item_id')->label('Item')->searchable()->required()
                ->options(fn (Forms\Get $get) => match ($get('item_type')) {
                    StockPlacement::MATERIAL => Material::query()->orderBy('name')->limit(300)->pluck('name', 'id')->all(),
                    StockPlacement::PART => Part::query()->orderBy('name')->limit(300)->pluck('name', 'id')->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all(),
                    StockPlacement::AGGREGATE => AggregateItemType::query()->orderBy('name')->pluck('name', 'id')->all(),
                    default => [],
                }),
            Forms\Components\TextInput::make('quantity')->label('Quantidade neste endereço')->numeric()->minValue(0)->required(),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('item_type')->label('Tipo')->formatStateUsing(fn ($state) => StockPlacement::TIPOS[$state] ?? $state)->badge(),
                Tables\Columns\TextColumn::make('nome')->label('Item')->state(fn (StockPlacement $r) => $r->nomeDoItem())->weight('bold'),
                Tables\Columns\TextColumn::make('quantity')->label('Quantidade')->numeric(3, ',', '.'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('Colocar item neste endereço')->mutateFormDataUsing(fn (array $data) => $data + ['tenant_id' => auth()->user()->tenant_id])])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
