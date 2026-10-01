<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InsumoTypeResource\Pages;

/** Insumos e Consumíveis (combustível, Arla, filtros, óleos...): mesma lógica de AggregateItemTypeResource, categoria insumo. */
class InsumoTypeResource extends AggregateItemTypeResource
{
    protected static string $category = 'insumo';

    protected static ?string $slug = 'insumo-tipos';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationParentItem = 'Insumos e Consumíveis';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Tipos de Insumo';

    protected static ?string $modelLabel = 'Tipo de Insumo';

    protected static ?string $pluralModelLabel = 'Tipo de Insumos';

    public static function getPages(): array
    {
        return ['index' => Pages\ManageInsumoTypes::route('/')];
    }
}
