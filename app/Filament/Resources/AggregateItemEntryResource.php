<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AggregateItemEntryResource\Pages;
use App\Models\AggregateItemEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AggregateItemEntryResource extends BaseResource
{
    protected static ?string $model = AggregateItemEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationGroup = 'Itens Agregados';

    protected static ?string $navigationParentItem = 'Acessórios e Componentes';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Entradas (Compras)';

    protected static ?string $modelLabel = 'Entrada de Item Agregado';

    protected static ?string $pluralModelLabel = 'Entradas de Itens Agregados';

    /** Categoria de Itens Agregados que esta tela controla (acessorio|insumo). */
    protected static string $category = 'acessorio';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('type', fn ($q) => $q->where('category', static::$category));
    }

    public static function form(Form $form): Form
    {
        $total = fn (Get $get, Set $set) => $set('total', number_format((float) $get('unit_price') * (int) $get('quantity'), 2, '.', ''));

        return $form->schema([
            Forms\Components\DatePicker::make('entry_date')->label('Data de entrada')->default(now())->required(),
            Forms\Components\Select::make('aggregate_item_type_id')->label('Tipo de item')
                ->relationship('type', 'name', fn ($query) => $query->where('category', static::$category))->searchable()->preload()->required(),
            Forms\Components\Select::make('supplier_id')->label('Fornecedor')
                ->relationship('supplier', 'name')->searchable()->preload(),
            Forms\Components\TextInput::make('invoice_number')->label('Nota fiscal')->maxLength(100),
            Forms\Components\TextInput::make('unit_price')->label('Preço unitário')->numeric()->prefix('R$')
                ->default(0)->minValue(0)->required()->live(onBlur: true)->afterStateUpdated($total),
            Forms\Components\TextInput::make('quantity')->label('Quantidade')->numeric()->integer()
                ->minValue(1)->required()->live(onBlur: true)->afterStateUpdated($total),
            Forms\Components\TextInput::make('total')->label('Total')->prefix('R$')->disabled()->dehydrated(false),
            Forms\Components\Textarea::make('notes')->label('Observações')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entry_date')->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('type.name')->label('Item')->searchable(),
                Tables\Columns\TextColumn::make('supplier.name')->label('Fornecedor')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('unit_price')->label('Preço unit.')->money('BRL'),
                Tables\Columns\TextColumn::make('quantity')->label('Qtd.'),
                Tables\Columns\TextColumn::make('total')->label('Total')->money('BRL')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('BRL')),
                Tables\Columns\TextColumn::make('invoice_number')->label('NF')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('entry_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('aggregate_item_type_id')->label('Item')->relationship('type', 'name', fn ($query) => $query->where('category', static::$category)),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAggregateItemEntries::route('/')];
    }
}
