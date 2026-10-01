<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AggregateItemTypeResource\Pages;
use App\Models\AggregateItemType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

class AggregateItemTypeResource extends BaseResource
{
    protected static ?string $model = AggregateItemType::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Itens Agregados';

    protected static ?string $navigationParentItem = 'Gestão de Itens Agregados';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Tipos de Item Agregado';

    protected static ?string $modelLabel = 'Tipo de Item Agregado';

    protected static ?string $pluralModelLabel = 'Tipos de Item Agregado';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nome')
                ->placeholder('Ex: Bandeja de Contenção, Cabo, Mangueira')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('inspection_interval_days')
                ->label('Intervalo padrão de inspeção (dias)')
                ->helperText('Usado para calcular o vencimento de cada unidade nova. Deixe vazio se não vence.')
                ->numeric()
                ->minValue(1),
            Forms\Components\Textarea::make('description')
                ->label('Descrição')
                ->rows(3)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('inspection_interval_days')->label('Inspeção a cada')->suffix(' dias')->placeholder('—'),
                Tables\Columns\TextColumn::make('saldo')->label('Saldo em estoque')->badge()
                    ->getStateUsing(fn (AggregateItemType $record) => $record->balance())
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('items_count')->label('Unidades')->counts('items')->badge()->color('gray'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, AggregateItemType $record) {
                        if ($record->items()->exists()) {
                            Notification::make()
                                ->danger()->title('Tipo em uso')
                                ->body('Existem unidades cadastradas deste tipo.')->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAggregateItemTypes::route('/')];
    }
}
