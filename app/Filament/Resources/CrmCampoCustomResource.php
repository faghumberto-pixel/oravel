<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrmCampoCustomResource\Pages;
use App\Models\CrmCampoCustom;
use App\Support\CrmCamposCustom;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

/** Campos extras que cada empresa cria para Leads e Clientes (valores em `custom` de cada registro). */
class CrmCampoCustomResource extends BaseResource
{
    protected static ?string $model = CrmCampoCustom::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'Campos Personalizados';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static ?int $navigationSort = 23;

    protected static ?string $modelLabel = 'Campo Personalizado';

    protected static ?string $pluralModelLabel = 'Campos Personalizados';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')->label('Nome do campo')->required()->maxLength(80),
            Forms\Components\Select::make('entidade')
                ->label('Aparece em')
                ->options(CrmCampoCustom::entidadeLabels())
                ->required()
                ->disabled(fn (?CrmCampoCustom $record) => (bool) $record)
                ->dehydrated(),
            Forms\Components\Select::make('tipo')
                ->label('Tipo')
                ->options(CrmCampoCustom::tipoLabels())
                ->required()
                ->live()
                ->disabled(fn (?CrmCampoCustom $record) => (bool) $record)
                ->dehydrated()
                ->helperText('O tipo não pode ser mudado depois de criado, para não bagunçar o que já foi preenchido.'),
            Forms\Components\TagsInput::make('opcoes')
                ->label('Opções da lista')
                ->placeholder('Digite e tecle Enter')
                ->visible(fn (Forms\Get $get) => in_array($get('tipo'), [CrmCampoCustom::TIPO_LISTA, CrmCampoCustom::TIPO_LISTA_MULTIPLA], true))
                ->required(fn (Forms\Get $get) => in_array($get('tipo'), [CrmCampoCustom::TIPO_LISTA, CrmCampoCustom::TIPO_LISTA_MULTIPLA], true))
                ->columnSpanFull(),
            Forms\Components\Toggle::make('obrigatorio')->label('Obrigatório')->default(false),
            Forms\Components\TextInput::make('ordem')->label('Ordem')->numeric()->default(0),
            Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Campo')->searchable(),
                Tables\Columns\TextColumn::make('entidade')->label('Aparece em')->badge()
                    ->formatStateUsing(fn ($state) => CrmCampoCustom::entidadeLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->formatStateUsing(fn ($state) => CrmCampoCustom::tipoLabels()[$state] ?? $state),
                Tables\Columns\IconColumn::make('obrigatorio')->label('Obrigatório')->boolean(),
                Tables\Columns\TextColumn::make('ordem')->label('Ordem')->sortable(),
                Tables\Columns\IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('entidade')->label('Aparece em')->options(CrmCampoCustom::entidadeLabels()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->after(fn () => CrmCamposCustom::esquecer()),
                Tables\Actions\DeleteAction::make()
                    ->before(function (CrmCampoCustom $record, Tables\Actions\DeleteAction $action) {
                        if (($emUso = $record->emUso()) > 0) {
                            Notification::make()->danger()
                                ->title('Campo em uso')
                                ->body("Este campo já está preenchido em {$emUso} registro(s). Desative-o em vez de excluir.")
                                ->send();
                            $action->cancel();
                        }
                    })
                    ->after(fn () => CrmCamposCustom::esquecer()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCrmCampoCustoms::route('/')];
    }
}
