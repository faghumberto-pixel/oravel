<?php

namespace App\Relacionamento\Filament\Resources;

use App\Relacionamento\Filament\Resources\RelScoreRegraResource\Pages\ManageRelScoreRegras;
use App\Relacionamento\Models\RelInteracao;
use App\Relacionamento\Models\RelScoreRegra;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Pesos do score de relacionamento, editaveis direto na lista. So' super admin. */
class RelScoreRegraResource extends Resource
{
    protected static ?string $model = RelScoreRegra::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Relacionamento Oravel';

    protected static ?string $navigationLabel = 'Pontuação do score';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'regra de pontuação';

    protected static ?string $pluralModelLabel = 'Pontuação do score';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Quantos pontos cada ação vale (o score vai de 0 a 100). "Máx. contagens" limita quantas vezes a mesma ação soma, para um acesso repetido não inflar a nota.')
            ->columns([
                Tables\Columns\TextColumn::make('tipo')->label('Ação')->formatStateUsing(fn (string $state): string => RelInteracao::rotulo($state)),
                Tables\Columns\TextInputColumn::make('pontos')->label('Pontos')->type('number')->rules(['required', 'integer', 'min:0', 'max:100']),
                Tables\Columns\TextInputColumn::make('limite')->label('Máx. contagens')->type('number')->rules(['required', 'integer', 'min:0', 'max:50']),
                Tables\Columns\ToggleColumn::make('ativo')->label('Ativa'),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRelScoreRegras::route('/')];
    }
}
