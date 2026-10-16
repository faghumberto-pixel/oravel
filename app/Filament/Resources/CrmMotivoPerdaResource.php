<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrmMotivoPerdaResource\Pages;
use App\Models\CrmMotivoPerda;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/** Lista editável do CRM por tenant. O lead guarda só o slug, então renomear não quebra dado antigo. */
class CrmMotivoPerdaResource extends BaseResource
{
    protected static ?string $model = CrmMotivoPerda::class;

    protected static ?string $navigationIcon = 'heroicon-o-hand-thumb-down';

    protected static ?string $navigationLabel = 'Motivos de Perda';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static ?int $navigationSort = 21;

    protected static ?string $modelLabel = 'Motivo de Perda';

    protected static ?string $pluralModelLabel = 'Motivos de Perda';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(80),
            Forms\Components\Toggle::make('exige_detalhe')->label('Exige detalhe (ex.: qual concorrente?)'),
            Forms\Components\TextInput::make('ordem')->label('Ordem')->numeric()->default(0),
            Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Nome')->searchable(),
                Tables\Columns\IconColumn::make('exige_detalhe')->label('Exige detalhe')->boolean(),
                Tables\Columns\TextColumn::make('ordem')->label('Ordem')->sortable(),
                Tables\Columns\IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCrmMotivoPerdas::route('/')];
    }
}
