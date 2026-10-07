<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AggregateItemExitResource\Pages;
use App\Models\AggregateItem;
use App\Models\AggregateItemExit;
use App\Models\AggregateItemType;
use App\Models\Asset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AggregateItemExitResource extends BaseResource
{
    protected static ?string $model = AggregateItemExit::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Itens Agregados';

    protected static ?string $navigationParentItem = 'Acessórios e Componentes';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Saídas (para Equipamento)';

    protected static ?string $modelLabel = 'Saída de Item Agregado';

    protected static ?string $pluralModelLabel = 'Saídas de Itens Agregados';

    /** Categoria de Itens Agregados que esta tela controla (acessorio|insumo). */
    protected static string $category = 'acessorio';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('type', fn ($q) => $q->where('category', static::$category));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('exit_date')->label('Data de saída')->default(now())->required(),
            Forms\Components\Select::make('aggregate_item_type_id')->label('Tipo de item')
                ->relationship('type', 'name', fn ($query) => $query->where('category', static::$category))->searchable()->preload()->required()->live(),
            Forms\Components\Select::make('aggregate_item_id')->label('Unidade específica (opcional)')
                ->helperText('Escolha se este item é controlado unidade a unidade.')
                ->relationship('item', 'code', fn ($query, Get $get) => $query
                    ->where('aggregate_item_type_id', $get('aggregate_item_type_id'))
                    ->where('status', AggregateItem::STATUS_DISPONIVEL))
                ->searchable()->preload()
                ->visible(fn () => static::$category === 'acessorio'),
            Forms\Components\TextInput::make('quantity')->label('Quantidade')->numeric()->integer()
                ->default(1)->minValue(1)->required()
                ->rules([
                    fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                        $type = AggregateItemType::find($get('aggregate_item_type_id'));

                        if ($type && (int) $value > $type->balance()) {
                            $fail("Saldo insuficiente: há {$type->balance()} em estoque.");
                        }
                    },
                ]),
            Forms\Components\Select::make('asset_id')->label('Equipamento de destino')
                ->relationship('asset', 'name')->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Asset::opcoesPesquisa($search))
                ->getOptionLabelFromRecordUsing(fn (Asset $a) => $a->selectLabel())->preload()->required(),
            Forms\Components\Select::make('reason')->label('Motivo')
                ->options(AggregateItemExit::reasonLabels())->default(AggregateItemExit::REASON_LOCACAO)
                ->native(false)->required(),
            Forms\Components\Select::make('maintenance_order_id')->label('Ordem de Serviço (opcional)')
                ->relationship('maintenanceOrder', 'os_number')->searchable()->preload(),
            Forms\Components\Textarea::make('notes')->label('Observações')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('exit_date')->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('type.name')->label('Item')->searchable(),
                Tables\Columns\TextColumn::make('item.code')->label('Unidade')->placeholder('—'),
                Tables\Columns\TextColumn::make('quantity')->label('Qtd.'),
                Tables\Columns\TextColumn::make('asset.name')->label('Equipamento')->searchable(),
                Tables\Columns\TextColumn::make('reason')->label('Motivo')
                    ->formatStateUsing(fn ($state) => AggregateItemExit::reasonLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('maintenanceOrder.os_number')->label('OS')->placeholder('—'),
                Tables\Columns\IconColumn::make('returned')->label('Devolvido')->boolean(),
                Tables\Columns\TextColumn::make('returned_at')->label('Data devolução')->date('d/m/Y')->placeholder('—'),
                Tables\Columns\TextColumn::make('returned_condition')->label('Estado')->badge()->placeholder('—')
                    ->formatStateUsing(fn ($state) => AggregateItemExit::conditionLabels()[$state] ?? $state)
                    ->color(fn ($state) => $state === AggregateItemExit::CONDITION_OK ? 'success' : 'danger'),
            ])
            ->defaultSort('exit_date', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('returned')->label('Devolvido'),
                Tables\Filters\SelectFilter::make('aggregate_item_type_id')->label('Item')->relationship('type', 'name', fn ($query) => $query->where('category', static::$category)),
            ])
            ->actions([
                Tables\Actions\Action::make('register_return')
                    ->label('Registrar devolução')->icon('heroicon-o-arrow-uturn-left')->color('warning')
                    ->visible(fn (AggregateItemExit $record) => ! $record->returned)
                    ->form([
                        Forms\Components\Select::make('returned_condition')->label('Estado do item')
                            ->options(AggregateItemExit::conditionLabels())->default(AggregateItemExit::CONDITION_OK)->required(),
                        Forms\Components\Textarea::make('notes')->label('Observações'),
                    ])
                    ->action(function (AggregateItemExit $record, array $data) {
                        $record->registerReturn($data['returned_condition'], $data['notes'] ?? null);
                        Notification::make()->title('Devolução registrada')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAggregateItemExits::route('/')];
    }
}
