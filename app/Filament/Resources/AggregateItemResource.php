<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AggregateItemResource\Pages;
use App\Models\AggregateItem;
use App\Models\Asset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AggregateItemResource extends BaseResource
{
    protected static ?string $model = AggregateItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'Itens Agregados';

    protected static ?string $navigationParentItem = 'Acessórios e Componentes';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Itens Agregados';

    protected static ?string $modelLabel = 'Item Agregado';

    protected static ?string $pluralModelLabel = 'Itens Agregados';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('type', fn ($q) => $q->where('category', 'acessorio'));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')->schema([
                Forms\Components\Select::make('aggregate_item_type_id')
                    ->label('Tipo')
                    ->relationship('type', 'name', fn ($query) => $query->where('category', 'acessorio'))
                    ->searchable()->preload()->required(),
                Forms\Components\TextInput::make('code')
                    ->label('Código / Patrimônio')
                    ->required()->maxLength(100)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->whereNull('deleted_at')),
                Forms\Components\TextInput::make('serial_number')->label('Nº de série')->maxLength(100),
                Forms\Components\TextInput::make('description')->label('Descrição')->maxLength(255),
            ])->columns(2),

            Forms\Components\Section::make('Situação')->schema([
                Forms\Components\Select::make('status')
                    ->label('Status')->options(AggregateItem::statusLabels())
                    ->default(AggregateItem::STATUS_DISPONIVEL)->required()->native(false),
                Forms\Components\Select::make('condition')
                    ->label('Estado de conservação')->options(AggregateItem::conditionLabels())
                    ->default('bom')->required()->native(false),
                Forms\Components\Select::make('asset_id')
                    ->label('Acompanhando o equipamento')
                    ->helperText('Equipamento ao qual este item está vinculado agora. Vazio = avulso/em estoque.')
                    ->relationship('asset', 'name')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Asset::opcoesPesquisa($search))
                    ->getOptionLabelFromRecordUsing(fn (Asset $a) => $a->selectLabel())->preload(),
            ])->columns(3),

            Forms\Components\Section::make('Compra e vencimento')->schema([
                Forms\Components\Select::make('supplier_id')
                    ->label('Fornecedor')->relationship('supplier', 'name')->searchable()->preload(),
                Forms\Components\TextInput::make('invoice_number')->label('Nota fiscal')->maxLength(100),
                Forms\Components\DatePicker::make('purchase_date')->label('Data da compra'),
                Forms\Components\TextInput::make('purchase_value')->label('Valor da compra')->numeric()->prefix('R$'),
                Forms\Components\DatePicker::make('warranty_until')->label('Garantia até'),
                Forms\Components\DatePicker::make('next_inspection_date')
                    ->label('Vencimento (próxima inspeção)')
                    ->helperText('Se vazio, é calculado pelo intervalo padrão do tipo.'),
            ])->columns(3),

            Forms\Components\Textarea::make('notes')->label('Observações')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Código')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('type.name')->label('Tipo')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn ($state) => AggregateItem::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        AggregateItem::STATUS_DISPONIVEL => 'success',
                        AggregateItem::STATUS_LOCADO => 'info',
                        AggregateItem::STATUS_MANUTENCAO => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('condition')->label('Conservação')
                    ->formatStateUsing(fn ($state) => AggregateItem::conditionLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('asset.name')->label('Equipamento')->placeholder('Avulso'),
                Tables\Columns\TextColumn::make('next_inspection_date')->label('Vencimento')->date('d/m/Y')->sortable()
                    ->placeholder('—')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : ($state && $state->diffInDays(now()) <= 30 ? 'warning' : null)),
            ])
            ->defaultSort('code')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(AggregateItem::statusLabels()),
                Tables\Filters\SelectFilter::make('aggregate_item_type_id')->label('Tipo')->relationship('type', 'name', fn ($query) => $query->where('category', 'acessorio')),
                Tables\Filters\Filter::make('vencidos')->label('Vencidos')
                    ->query(fn ($query) => $query->whereDate('next_inspection_date', '<', now())),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAggregateItems::route('/'),
            'create' => Pages\CreateAggregateItem::route('/create'),
            'edit' => Pages\EditAggregateItem::route('/{record}/edit'),
        ];
    }
}
