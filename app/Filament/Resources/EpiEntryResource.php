<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EpiEntryResource\Pages;
use App\Models\EpiEntry;
use App\Models\Material;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EpiEntryResource extends BaseResource
{
    protected static ?string $model = EpiEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationGroup = 'EPI';

    protected static ?string $navigationParentItem = 'Gestão de EPI';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Entradas (Compras)';

    protected static ?string $modelLabel = 'Entrada de EPI';

    protected static ?string $pluralModelLabel = 'Entradas de EPI';

    public static function form(Form $form): Form
    {
        $total = fn (Get $get, Set $set) => $set('total', number_format((float) $get('unit_price') * (int) $get('quantity'), 2, '.', ''));

        return $form->schema([
            Forms\Components\DatePicker::make('entry_date')->label('Data de entrada')->default(now())->required(),
            Forms\Components\Select::make('material_id')->label('EPI')
                ->relationship('material', 'name', fn (Builder $query) => $query
                    ->where('tenant_id', Tenancy::current()?->id)->whereHas('epiSpecification'))
                ->getOptionLabelFromRecordUsing(fn (Material $record) => $record->name.($record->epiSpecification?->size_label ? ' — '.$record->epiSpecification->size_label : ''))
                ->searchable()->preload()->required(),
            Forms\Components\Select::make('internal_unit_id')->label('Filial (entrada no estoque)')
                ->relationship('internalUnit', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                ->searchable()->preload()->required(),
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
                Tables\Columns\TextColumn::make('material.name')->label('EPI')->searchable(),
                Tables\Columns\TextColumn::make('supplier.name')->label('Fornecedor')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('unit_price')->label('Preço unit.')->money('BRL'),
                Tables\Columns\TextColumn::make('quantity')->label('Qtd.'),
                Tables\Columns\TextColumn::make('total')->label('Total')->money('BRL')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('BRL')),
                Tables\Columns\TextColumn::make('internalUnit.name')->label('Filial')->toggleable(),
                Tables\Columns\TextColumn::make('invoice_number')->label('NF')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('entry_date', 'desc')
            // Editar/excluir uma entrada nao reverte o estoque ja creditado --
            // correcoes de estoque passam por ajuste/inventario, nao aqui.
            ->actions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageEpiEntries::route('/')];
    }
}
