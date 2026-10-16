<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrmOrigemResource\Pages;
use App\Models\CrmOrigem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/** Lista editável do CRM por tenant. O lead guarda só o slug, então renomear não quebra dado antigo. */
class CrmOrigemResource extends BaseResource
{
    protected static ?string $model = CrmOrigem::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-on-square';

    protected static ?string $navigationLabel = 'Origens de Lead';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static ?int $navigationSort = 22;

    protected static ?string $modelLabel = 'Origem';

    protected static ?string $pluralModelLabel = 'Origens';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(80),

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
        return ['index' => Pages\ListCrmOrigems::route('/')];
    }
}
