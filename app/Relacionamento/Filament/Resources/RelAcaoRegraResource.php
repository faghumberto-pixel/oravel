<?php

namespace App\Relacionamento\Filament\Resources;

use App\Relacionamento\Filament\Resources\RelAcaoRegraResource\Pages\ManageRelAcaoRegras;
use App\Relacionamento\Models\RelAcaoRegra;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Regras "faixa de score => proxima acao comercial". So' super admin. */
class RelAcaoRegraResource extends Resource
{
    protected static ?string $model = RelAcaoRegra::class;

    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';

    protected static ?string $navigationGroup = 'Relacionamento Oravel';

    protected static ?string $navigationLabel = 'Próxima ação';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'regra de próxima ação';

    protected static ?string $pluralModelLabel = 'Próxima ação comercial';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('score_min')->label('Score de')->numeric()->minValue(0)->maxValue(100)->required(),
            Forms\Components\TextInput::make('score_max')->label('Score até')->numeric()->minValue(0)->maxValue(100)->required()->gte('score_min'),
            Forms\Components\TextInput::make('titulo')->label('Ação sugerida')->required()->maxLength(120)->columnSpanFull(),
            Forms\Components\Textarea::make('descricao')->label('Orientação para a equipe')->rows(3)->columnSpanFull(),
            Forms\Components\Toggle::make('ativo')->label('Ativa')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Qual ação sugerir conforme o score da empresa. Se duas regras cobrirem o mesmo score, vale a de faixa mais alta.')
            ->columns([
                Tables\Columns\TextColumn::make('score_min')->label('De')->sortable(),
                Tables\Columns\TextColumn::make('score_max')->label('Até'),
                Tables\Columns\TextColumn::make('titulo')->label('Ação')->weight('bold'),
                Tables\Columns\TextColumn::make('descricao')->label('Orientação')->wrap()->limit(110),
                Tables\Columns\ToggleColumn::make('ativo')->label('Ativa'),
            ])
            ->defaultSort('score_min', 'desc')
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRelAcaoRegras::route('/')];
    }
}
