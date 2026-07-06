<?php

namespace App\Filament\Resources\PreventiveMaintenanceExecutionResource\RelationManagers;

use App\Models\Material;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaterialsRelationManager extends RelationManager
{
    protected static string $relationship = 'materials';

    protected static ?string $title = 'Peças Utilizadas';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('material_id')
                ->label('Material')
                ->relationship('material', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                ->searchable()
                ->preload()
                ->live()
                ->required(),

            Forms\Components\TextInput::make('quantity')
                ->label('Quantidade')
                ->numeric()
                ->required(),

            Forms\Components\TextInput::make('serial_number')
                ->label('Número de Série')
                ->visible(fn (Get $get) => (bool) Material::find($get('material_id'))?->requires_serial_number)
                ->required(fn (Get $get) => (bool) Material::find($get('material_id'))?->requires_serial_number),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('material.name')->label('Material'),
                Tables\Columns\TextColumn::make('quantity')->label('Qtd.'),
                Tables\Columns\TextColumn::make('serial_number')->label('Nº de Série')->placeholder('—'),
                Tables\Columns\TextColumn::make('unit_price')->label('Custo Unit.')->money('BRL'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
