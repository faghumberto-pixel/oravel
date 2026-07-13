<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceStatusHistoryResource\Pages;
use App\Models\MaintenanceStatusHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Historico de troca de status de Ordem de Manutencao (ja gravado de
 * verdade pelo Kanban, ver MaintenanceKanban.php) como comprovacao de
 * auditoria -- so leitura, so admin do tenant
 * (MaintenanceStatusHistoryPolicy).
 */
class MaintenanceStatusHistoryResource extends Resource
{
    protected static ?string $model = MaintenanceStatusHistory::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Histórico de Status de OS';

    protected static ?string $modelLabel = 'Histórico de Status';

    protected static ?string $pluralModelLabel = 'Histórico de Status de OS';

    protected static ?int $navigationSort = 21;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceStatusHistory::class);
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
        // whereHas('maintenanceOrder') ja aplica o global scope de
        // BelongsToTenant de MaintenanceOrder (bypass de super admin
        // incluso) -- nao precisa filtrar tenant_id na mao aqui.
        return parent::getEloquentQuery()->whereHas('maintenanceOrder');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('maintenanceOrder.os_number')->label('OS')->disabled(),
            Forms\Components\TextInput::make('old_status')->label('Status Anterior')->disabled(),
            Forms\Components\TextInput::make('new_status')->label('Novo Status')->disabled(),
            Forms\Components\Textarea::make('observation')->label('Observação')->disabled(),
            Forms\Components\TextInput::make('user.name')->label('Responsável')->disabled(),
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

                Tables\Columns\TextColumn::make('maintenanceOrder.os_number')
                    ->label('OS')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('maintenanceOrder.asset.name')
                    ->label('Ativo')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('old_status')
                    ->label('De')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('new_status')
                    ->label('Para')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('observation')
                    ->label('Observação')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Responsável')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('maintenanceOrder.tenant.name')
                    ->label('Tenant')
                    ->badge()
                    ->color('gray')
                    ->visible(fn () => (bool) auth()->user()?->isSuperAdmin())
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('new_status')
                    ->label('Novo Status')
                    ->options(fn () => MaintenanceStatusHistory::query()
                        ->whereHas('maintenanceOrder')
                        ->distinct()
                        ->pluck('new_status', 'new_status')
                        ->all()),

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
            'index' => Pages\ListMaintenanceStatusHistories::route('/'),
        ];
    }
}
