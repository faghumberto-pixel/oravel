<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Resources\MaterialStockTakeResource\Pages;
use App\Filament\Resources\MaterialStockTakeResource\RelationManagers\ItemsRelationManager;
use App\Models\MaterialStockTake;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Inventario -- contagem fisica de estoque comparada com o saldo do
 * sistema. Nasce em rascunho com uma linha por Material (populateFromMaterials(),
 * disparado no afterCreate da pagina de criacao), o usuario preenche a
 * quantidade contada aba a aba (ItemsRelationManager), e finaliza via
 * action dedicada (MaterialStockTake::finalize()) -- so' ai' os ajustes
 * viram MaterialStockMovement de verdade e corrigem Material.current_stock.
 */
#[BelongsToFeature('material_stock_takes')]
class MaterialStockTakeResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = MaterialStockTake::class;

    protected static ?string $modelLabel = 'Inventário';

    protected static ?string $pluralModelLabel = 'Inventários';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    public static function canEdit($record): bool
    {
        return $record->status === MaterialStockTake::STATUS_RASCUNHO
            && (bool) auth()->user()?->can('update', $record);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('internal_unit_id')
                ->label('Filial')
                ->relationship('internalUnit', 'name', fn ($query) => $query->where('tenant_id', Tenancy::current()?->id))
                ->required()
                ->disabled(fn (?MaterialStockTake $record) => $record !== null)
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('conducted_by_user_id')
                ->label('Conduzido por')
                ->relationship('conductedBy', 'name', fn ($query) => $query->where('tenant_id', Tenancy::current()?->id))
                ->default(fn () => auth()->id())
                ->required(),
            Forms\Components\Textarea::make('notes')
                ->label('Observações')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Iniciado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('internalUnit.name')
                    ->label('Filial'),
                Tables\Columns\TextColumn::make('conductedBy.name')
                    ->label('Conduzido por'),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Itens')
                    ->counts('items'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Situação')
                    ->colors([
                        'warning' => MaterialStockTake::STATUS_RASCUNHO,
                        'success' => MaterialStockTake::STATUS_FINALIZADO,
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        MaterialStockTake::STATUS_RASCUNHO => 'Em Andamento',
                        MaterialStockTake::STATUS_FINALIZADO => 'Finalizado',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('finished_at')
                    ->label('Finalizado em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        MaterialStockTake::STATUS_RASCUNHO => 'Em Andamento',
                        MaterialStockTake::STATUS_FINALIZADO => 'Finalizado',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (MaterialStockTake $record) => $record->status === MaterialStockTake::STATUS_RASCUNHO),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterialStockTakes::route('/'),
            'create' => Pages\CreateMaterialStockTake::route('/create'),
            'edit' => Pages\EditMaterialStockTake::route('/{record}/edit'),
        ];
    }
}
