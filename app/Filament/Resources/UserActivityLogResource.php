<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserActivityLogResource\Pages;
use App\Models\UserActivityLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Log de atividade dos usuarios (navegacao + criacao/edicao/exclusao de
 * registros) -- so leitura, so admin do tenant. Ver LogUserActivity
 * (middleware) e AppServiceProvider::registerActivityLogging() para como
 * os registros sao gerados.
 */
class UserActivityLogResource extends Resource
{
    protected static ?string $model = UserActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'Logs de Atividade';

    protected static ?string $modelLabel = 'Log de Atividade';

    protected static ?string $pluralModelLabel = 'Logs de Atividade';

    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isAdmin();
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('user_activity_logs.*')
            ->selectRaw('LEAD(created_at) OVER (PARTITION BY user_id ORDER BY created_at) as next_created_at');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('resource_label')->label('Tela/Recurso')->disabled(),
            Forms\Components\TextInput::make('action')->label('Ação')->disabled(),
            Forms\Components\TextInput::make('path')->label('Caminho')->disabled(),
            Forms\Components\Textarea::make('subject_type')->label('Tipo do Registro')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuário')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('resource_label')
                    ->label('Tela/Recurso')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('action')
                    ->label('Ação')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        UserActivityLog::ACTION_VIEW => 'Visualizou',
                        UserActivityLog::ACTION_CREATED => 'Criou',
                        UserActivityLog::ACTION_UPDATED => 'Editou',
                        UserActivityLog::ACTION_DELETED => 'Excluiu',
                        UserActivityLog::ACTION_LOGIN => 'Login',
                        UserActivityLog::ACTION_LOGOUT => 'Logout',
                        default => $state ?? '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        UserActivityLog::ACTION_CREATED, UserActivityLog::ACTION_LOGIN => 'success',
                        UserActivityLog::ACTION_UPDATED => 'warning',
                        UserActivityLog::ACTION_DELETED, UserActivityLog::ACTION_LOGOUT => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('path')
                    ->label('Caminho')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(40),

                Tables\Columns\TextColumn::make('tempo_na_tela')
                    ->label('Tempo na Tela')
                    ->state(function (UserActivityLog $record) {
                        $next = $record->getAttribute('next_created_at');

                        if (! $next) {
                            return '—';
                        }

                        // Carbon 3: diffInSeconds() sem `true` retorna float com sinal
                        // dependendo da ordem -- forca absoluto + inteiro pra exibicao.
                        $seconds = (int) Carbon::parse($record->created_at)
                            ->diffInSeconds(Carbon::parse($next), true);

                        if ($seconds < 60) {
                            return "{$seconds}s";
                        }

                        return floor($seconds / 60).'min '.($seconds % 60).'s';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Usuário')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('action')
                    ->label('Ação')
                    ->options([
                        UserActivityLog::ACTION_VIEW => 'Visualizou',
                        UserActivityLog::ACTION_CREATED => 'Criou',
                        UserActivityLog::ACTION_UPDATED => 'Editou',
                        UserActivityLog::ACTION_DELETED => 'Excluiu',
                        UserActivityLog::ACTION_LOGIN => 'Login',
                        UserActivityLog::ACTION_LOGOUT => 'Logout',
                    ]),

                Tables\Filters\Filter::make('periodo')
                    ->label('Intervalo de Data')
                    ->form([
                        Forms\Components\DatePicker::make('de')->label('De'),
                        Forms\Components\DatePicker::make('ate')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['de'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['ate'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
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
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserActivityLogs::route('/'),
        ];
    }
}
