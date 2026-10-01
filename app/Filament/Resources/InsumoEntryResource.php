<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InsumoEntryResource\Pages;

/** Insumos e Consumíveis (combustível, Arla, filtros, óleos...): mesma lógica de AggregateItemEntryResource, categoria insumo. */
class InsumoEntryResource extends AggregateItemEntryResource
{
    protected static string $category = 'insumo';

    protected static ?string $slug = 'insumo-entradas';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationParentItem = 'Insumos e Consumíveis';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Entradas (Compras)';

    protected static ?string $modelLabel = 'Entrada de Insumo';

    protected static ?string $pluralModelLabel = 'Entrada de Insumos';

    public static function getPages(): array
    {
        return ['index' => Pages\ManageInsumoEntries::route('/')];
    }
}
