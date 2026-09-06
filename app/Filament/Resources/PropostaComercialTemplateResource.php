<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropostaComercialTemplateResource\Pages;
use App\Models\PropostaComercialTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * CRUD raso -- o admin de cada tenant define seus próprios termos padrão
 * de proposta. Ao criar uma PropostaComercial, default_terms é copiado
 * (não referenciado) pra terms -- ver PropostaComercial::fillFromTemplate().
 */
class PropostaComercialTemplateResource extends BaseResource
{
    protected static ?string $model = PropostaComercialTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão Comercial';

    protected static ?string $modelLabel = 'Template de Proposta';

    protected static ?string $pluralModelLabel = 'Templates de Proposta';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Template de Proposta Comercial')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Padrão')
                        ->helperText('Usado automaticamente quando o vendedor não escolher um template específico.'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Ativo')
                        ->default(true),
                    Forms\Components\TextInput::make('default_valid_days')
                        ->label('Validade padrão (dias)')
                        ->numeric()
                        ->helperText('Sugere a data de validade da proposta -- editável pelo vendedor.'),
                    Forms\Components\Textarea::make('default_terms')
                        ->label('Termos padrão')
                        ->rows(8)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Padrão')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPropostaComercialTemplates::route('/'),
            'create' => Pages\CreatePropostaComercialTemplate::route('/create'),
            'edit' => Pages\EditPropostaComercialTemplate::route('/{record}/edit'),
        ];
    }
}
