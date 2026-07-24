<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AbcMatrixHistoryResource\Pages;
use App\Models\AbcMatrixHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Histórico de mudança de nível da Matriz ABC (gravado por AbcMatrixObserver)
 * -- só leitura, mesmo padrão de MaintenanceStatusHistoryResource.
 */
class AbcMatrixHistoryResource extends Resource
{
    protected static ?string $model = AbcMatrixHistory::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Histórico de Matriz ABC';

    protected static ?string $modelLabel = 'Histórico de Matriz ABC';

    protected static ?string $pluralModelLabel = 'Histórico de Matriz ABC';

    protected static ?int $navigationSort = 22;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('viewAny', AbcMatrixHistory::class);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('asset.patrimonio')->label('Patrimônio')->disabled(),
            Forms\Components\TextInput::make('asset.name')->label('Ativo')->disabled(),
            Forms\Components\TextInput::make('nivel_anterior')->label('Nível Anterior')->disabled(),
            Forms\Components\TextInput::make('nivel_novo')->label('Nível Novo')->disabled(),
            Forms\Components\TextInput::make('changedBy.name')->label('Alterado por')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('changed_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('asset.patrimonio')
                    ->label('Patrimônio')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Ativo')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('nivel_anterior')
                    ->label('De')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('nivel_novo')
                    ->label('Para')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('changedBy.name')
                    ->label('Alterado por')
                    ->searchable()
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\Filter::make('periodo')
                    ->label('Intervalo de Data')
                    ->form([
                        Forms\Components\DatePicker::make('de')->label('De'),
                        Forms\Components\DatePicker::make('ate')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['de'] ?? null, fn (Builder $q, $date) => $q->whereDate('changed_at', '>=', $date))
                            ->when($data['ate'] ?? null, fn (Builder $q, $date) => $q->whereDate('changed_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['de'] ?? null) {
                            $indicators[] = 'De: '.Carbon::parse($data['de'])->format('d/m/Y');
                        }
                        if ($data['ate'] ?? null) {
                            $indicators[] = 'Até: '.Carbon::parse($data['ate'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
            ])
            ->defaultSort('changed_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAbcMatrixHistories::route('/'),
        ];
    }
}
