<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\TaxaConfiguracaoResource\Pages;
use App\Models\TaxaConfiguracao;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Catálogo de taxas de configuração (pagamento único, opcionais): a Central edita nome, descrição, valor e se está à venda. */
class TaxaConfiguracaoResource extends Resource
{
    protected static ?string $model = TaxaConfiguracao::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $navigationLabel = 'Taxas de configuração';

    protected static ?string $modelLabel = 'taxa de configuração';

    protected static ?string $pluralModelLabel = 'taxas de configuração';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Taxa')->schema([
                Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(120),
                Forms\Components\TextInput::make('codigo')->label('Código')->required()->maxLength(40)->alphaDash()->unique(ignoreRecord: true)
                    ->disabled(fn (?TaxaConfiguracao $record) => $record?->exists === true)->helperText('Identificador interno; não muda depois de criado.'),
                Forms\Components\Textarea::make('descricao')->label('O que está incluído')->rows(3)->columnSpanFull(),
                Forms\Components\TextInput::make('valor')->label('Valor (pagamento único)')->numeric()->prefix('R$')->minValue(0)->step(0.01)
                    ->helperText('Vazio ou zero: a taxa não aparece no site nem no checkout.'),
                Forms\Components\Toggle::make('ativa')->label('Disponível para o cliente aprovar')->default(true),
                Forms\Components\TextInput::make('ordem')->label('Ordem')->numeric()->default(0),
                Forms\Components\TextInput::make('complemento_feature')->label('Depende do complemento (opcional)')->maxLength(80)
                    ->helperText('Chave do complemento (ex.: modulo_cobranca_automatica). Só gera um aviso ao cliente; não bloqueia a venda.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('ordem')->columns([
            Tables\Columns\TextColumn::make('nome')->label('Taxa')->weight('bold')->searchable(),
            Tables\Columns\TextColumn::make('valor')->label('Valor')->money('BRL')->placeholder('Sem valor'),
            Tables\Columns\IconColumn::make('ativa')->label('À venda')->boolean()
                ->state(fn (TaxaConfiguracao $r) => $r->ativa && (float) $r->valor > 0),
            Tables\Columns\TextColumn::make('codigo')->label('Código')->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('ordem')->label('Ordem')->sortable(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxasConfiguracao::route('/'),
            'create' => Pages\CreateTaxaConfiguracao::route('/create'),
            'edit' => Pages\EditTaxaConfiguracao::route('/{record}/edit'),
        ];
    }
}
