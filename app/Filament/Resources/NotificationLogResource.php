<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationLogResource\Pages;
use App\Models\NotificationLog;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Historico de notificacoes (sininho) como comprovacao de auditoria --
 * data/hora, categoria, quem recebeu, se/quando foi lida. So leitura, so
 * admin do tenant (NotificationLogPolicy). O sininho em si nao deixa mais
 * apagar notificacao (ver App\Livewire\DatabaseNotifications) -- esse
 * relatorio e' o jeito de navegar o historico completo, nao so o que
 * ainda esta na lista do sino.
 */
class NotificationLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Notificações';

    protected static ?string $modelLabel = 'Notificação';

    protected static ?string $pluralModelLabel = 'Notificações';

    protected static ?int $navigationSort = 20;

    private const CATEGORIES = [
        'success' => 'Sucesso',
        'warning' => 'Aviso',
        'danger' => 'Erro',
        'info' => 'Informativo',
    ];

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('viewAny', NotificationLog::class);
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
        $query = parent::getEloquentQuery()->where('notifiable_type', User::class);

        $user = Auth::user();

        if (! $user || $user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereIn('notifiable_id', User::where('tenant_id', $user->tenant_id)->pluck('id'));
    }

    private static function category(?array $data): string
    {
        return self::CATEGORIES[$data['status'] ?? 'info'] ?? 'Informativo';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('data.title')->label('Título')->disabled(),
            Forms\Components\Textarea::make('data.body')->label('Mensagem')->disabled(),
            Forms\Components\TextInput::make('notifiable.name')->label('Destinatário')->disabled(),
            Forms\Components\TextInput::make('created_at')->label('Enviada em')->disabled(),
            Forms\Components\TextInput::make('read_at')->label('Lida em')->disabled(),
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

                Tables\Columns\TextColumn::make('categoria')
                    ->label('Categoria')
                    ->state(fn (NotificationLog $record) => self::category($record->data))
                    ->badge()
                    ->color(fn (NotificationLog $record) => match ($record->data['status'] ?? 'info') {
                        'success' => 'success',
                        'warning' => 'warning',
                        'danger' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('data.title')
                    ->label('Título')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('data->title', 'ilike', "%{$search}%"))
                    ->wrap(),

                Tables\Columns\TextColumn::make('notifiable.name')
                    ->label('Destinatário')
                    ->description(fn (NotificationLog $record) => $record->notifiable?->email)
                    ->searchable(query: function (Builder $query, string $search) {
                        $ids = User::where('name', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%")
                            ->pluck('id');

                        return $query->whereIn('notifiable_id', $ids);
                    }),

                Tables\Columns\IconColumn::make('lida')
                    ->label('Lida')
                    ->state(fn (NotificationLog $record) => (bool) $record->read_at)
                    ->boolean(),

                Tables\Columns\TextColumn::make('read_at')
                    ->label('Lida em')
                    ->dateTime('d/m/Y H:i:s')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('notifiable.tenant.name')
                    ->label('Tenant')
                    ->badge()
                    ->color('gray')
                    ->visible(fn () => (bool) auth()->user()?->isSuperAdmin())
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categoria')
                    ->label('Categoria')
                    ->options(self::CATEGORIES)
                    ->query(function (Builder $query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $q, $status) => $q->where('data->status', $status),
                        );
                    }),

                Tables\Filters\TernaryFilter::make('lida')
                    ->label('Lida')
                    ->placeholder('Todas')
                    ->trueLabel('Lidas')
                    ->falseLabel('Não lidas')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('read_at'),
                        false: fn (Builder $query) => $query->whereNull('read_at'),
                        blank: fn (Builder $query) => $query,
                    ),

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
            'index' => Pages\ListNotificationLogs::route('/'),
        ];
    }
}
