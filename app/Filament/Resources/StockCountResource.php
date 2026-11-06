<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockCountResource\Pages;
use App\Filament\Resources\StockCountResource\RelationManagers\ItemsRelationManager;
use App\Models\InternalUnit;
use App\Models\StockCount;
use App\Models\StockGroup;
use App\Models\StockLocation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class StockCountResource extends BaseResource
{
    protected static ?string $model = StockCount::class;

    protected static ?string $modelLabel = 'Contagem física';

    protected static ?string $pluralModelLabel = 'Contagens Físicas';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Materiais e Peças';

    protected static ?string $navigationParentItem = 'Gestão de Estoque';

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nome da contagem')->placeholder('Ex.: Inventário de dezembro — corredor A')->required()->maxLength(160)->disabled(fn (?StockCount $record) => $record && ! $record->aberta()),
            Forms\Components\Select::make('internal_unit_id')->label('Almoxarifado')->options(fn () => InternalUnit::query()->orderBy('name')->pluck('name', 'id'))->searchable()->required()->disabledOn('edit'),
            Forms\Components\Select::make('stock_group_id')->label('Só deste grupo (opcional)')->options(fn () => StockGroup::query()->orderBy('sort_order')->pluck('name', 'id'))->placeholder('Todos os grupos')->disabledOn('edit'),
            Forms\Components\Select::make('aisle')->label('Só deste corredor (opcional)')->options(fn () => StockLocation::query()->distinct()->orderBy('aisle')->pluck('aisle', 'aisle'))->placeholder('Todos os corredores')->disabledOn('edit'),
            Forms\Components\Textarea::make('notes')->label('Observações')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['items', 'items as contados_count' => fn ($q) => $q->whereNotNull('counted_quantity')]))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Contagem')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('internalUnit.name')->label('Almoxarifado'),
                Tables\Columns\TextColumn::make('group.name')->label('Grupo')->placeholder('Todos'),
                Tables\Columns\TextColumn::make('aisle')->label('Corredor')->placeholder('Todos'),
                Tables\Columns\TextColumn::make('progresso')->label('Contados')->state(fn (StockCount $r) => $r->contados_count.' de '.$r->items_count)->badge()->color(fn (StockCount $r) => $r->items_count > 0 && $r->contados_count === $r->items_count ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('status')->label('Situação')->badge()->formatStateUsing(fn ($state) => $state === StockCount::FINALIZADA ? 'Finalizada' : 'Em contagem')->color(fn ($state) => $state === StockCount::FINALIZADA ? 'success' : 'info'),
                Tables\Columns\TextColumn::make('created_at')->label('Aberta em')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('finished_at')->label('Finalizada em')->dateTime('d/m/Y H:i')->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make()->label('Conferir'),
                Tables\Actions\Action::make('celular')->label('Contar no celular')->icon('heroicon-o-device-phone-mobile')->color('gray')
                    ->url(fn (StockCount $r) => route('estoque.contagem-mobile', ['contagem' => $r->id]))->openUrlInNewTab()->visible(fn (StockCount $r) => $r->aberta()),
            ]);
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockCounts::route('/'),
            'create' => Pages\CreateStockCount::route('/create'),
            'edit' => Pages\EditStockCount::route('/{record}/edit'),
        ];
    }
}
