<?php

namespace App\Filament\Resources\CrmPipelineResource\RelationManagers;

use App\Models\CrmEtapa;
use App\Support\CrmPalette;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** Etapas do funil: arraste para reordenar; probabilidade alimenta o valor ponderado do Kanban. */
class EtapasRelationManager extends RelationManager
{
    protected static string $relationship = 'etapas';

    protected static ?string $title = 'Etapas';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')->label('Nome da etapa')->required()->maxLength(80),
            Forms\Components\Select::make('tipo')
                ->label('Tipo')
                ->options(CrmEtapa::tipoLabels())
                ->default(CrmEtapa::TIPO_ABERTA)
                ->required()
                ->live()
                ->helperText('Ganho e Perdido encerram o lead. Perdido pede o motivo.'),
            Forms\Components\TextInput::make('probabilidade')
                ->label('Probabilidade de fechar (%)')
                ->numeric()->minValue(0)->maxValue(100)->default(10)
                ->disabled(fn (Forms\Get $get) => $get('tipo') !== CrmEtapa::TIPO_ABERTA)
                ->dehydrated(fn (Forms\Get $get) => $get('tipo') === CrmEtapa::TIPO_ABERTA)
                ->helperText('Usada no valor ponderado do funil. Ganho = 100%, Perdido = 0%.'),
            Forms\Components\Select::make('cor')->label('Cor')->options(CrmPalette::etapaCores()),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nome')
            ->reorderable('ordem')
            ->defaultSort('ordem')
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Etapa')->badge()
                    ->color(fn (CrmEtapa $r) => CrmPalette::etapa($r)['filament']),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->formatStateUsing(fn ($s) => CrmEtapa::tipoLabels()[$s] ?? $s),
                Tables\Columns\TextColumn::make('probabilidade')->label('Probabilidade')->suffix('%'),
                Tables\Columns\TextColumn::make('leads_count')->label('Leads')->counts('leads'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->mutateFormDataUsing(function (array $data) {
                    $funil = $this->getOwnerRecord();
                    $data['tenant_id'] = $funil->tenant_id;
                    $data['ordem'] = (int) $funil->etapas()->max('ordem') + 1;

                    return $data;
                }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->before(function (CrmEtapa $record, Tables\Actions\DeleteAction $action) {
                    $funil = $record->funil;
                    $ultimaDoTipo = ! $funil->etapas()->where('tipo', $record->tipo)->whereKeyNot($record->id)->exists();

                    if ($record->leads()->exists() || ($record->tipo !== CrmEtapa::TIPO_ABERTA && $ultimaDoTipo)) {
                        Notification::make()->danger()
                            ->title('Não dá para excluir esta etapa')
                            ->body('Há leads nela, ou ela é a única etapa final desse tipo no funil. Mova os leads antes.')
                            ->send();
                        $action->cancel();
                    }
                }),
            ]);
    }
}
