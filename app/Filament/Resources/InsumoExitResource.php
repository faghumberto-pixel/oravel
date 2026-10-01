<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InsumoExitResource\Pages;

/** Insumos e Consumíveis (combustível, Arla, filtros, óleos...): mesma lógica de AggregateItemExitResource, categoria insumo. */
class InsumoExitResource extends AggregateItemExitResource
{
    protected static string $category = 'insumo';

    protected static ?string $slug = 'insumo-saidas';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationParentItem = 'Insumos e Consumíveis';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Saídas (para Equipamento)';

    protected static ?string $modelLabel = 'Saída de Insumo';

    protected static ?string $pluralModelLabel = 'Saída de Insumos';

    public static function getPages(): array
    {
        return ['index' => Pages\ManageInsumoExits::route('/')];
    }
}
