<?php

namespace App\Filament\Pages;

use App\Models\MaterialStockMovement;
use App\Support\Tenancy;
use Filament\Filament;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class Inventario extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationParentItem = 'Almoxarifado';

    protected static ?string $navigationLabel = 'Histórico de Movimentação';

    protected static ?string $title = 'Inventário - Histórico de Movimentação';

    protected static string $view = 'filament.pages.inventario';

    protected static ?int $navigationSort = 6;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                MaterialStockMovement::query()
                    ->where('tenant_id', Tenancy::current()?->id)
                    ->with(['material', 'createdBy'])
            )
            ->columns([
                TextColumn::make('material.sku')
                    ->label('Código do Material')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('material.name')
                    ->label('Descrição')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('material.category.name')
                    ->label('Grupo')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn($state) => MaterialStockMovement::TYPES[$state] ?? $state)
                    ->badge()
                    ->color(fn($state) => $state === MaterialStockMovement::TYPE_ENTRADA ? 'success' : ($state === MaterialStockMovement::TYPE_SAIDA ? 'danger' : 'gray'))
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Quantidade')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                TextColumn::make('balance_after')
                    ->label('Total')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }
}
