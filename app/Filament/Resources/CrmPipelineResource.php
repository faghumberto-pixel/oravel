<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrmPipelineResource\Pages;
use App\Filament\Resources\CrmPipelineResource\RelationManagers\EtapasRelationManager;
use App\Models\CrmPipeline;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

/** Funis de vendas do CRM (cada funil tem as suas etapas, com probabilidade e tipo ganho/perdido). */
class CrmPipelineResource extends BaseResource
{
    protected static ?string $model = CrmPipeline::class;

    protected static ?string $navigationIcon = 'heroicon-o-funnel';

    protected static ?string $navigationLabel = 'Funis e Etapas';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Funil';

    protected static ?string $pluralModelLabel = 'Funis';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')->label('Nome do funil')->required()->maxLength(120),
            Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Funil')->searchable(),
                Tables\Columns\IconColumn::make('padrao')->label('Padrão')->boolean(),
                Tables\Columns\IconColumn::make('ativo')->label('Ativo')->boolean(),
                Tables\Columns\TextColumn::make('etapas_count')->label('Etapas')->counts('etapas'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (CrmPipeline $record, Tables\Actions\DeleteAction $action) {
                        if ($record->padrao || $record->etapas()->whereHas('leads')->exists()) {
                            Notification::make()->danger()
                                ->title('Não dá para excluir')
                                ->body('O funil padrão e funis com leads não podem ser excluídos. Desative o funil em vez disso.')
                                ->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [EtapasRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrmPipelines::route('/'),
            'create' => Pages\CreateCrmPipeline::route('/create'),
            'edit' => Pages\EditCrmPipeline::route('/{record}/edit'),
        ];
    }
}
