<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HorimeterReadingResource\Pages;
use App\Models\Asset;
use App\Models\Client;
use App\Models\HorimeterReading;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Monitoramento geral de atualizações de horímetro -- todo apontamento
 * feito no ERP por qualquer meio (mobile offline via
 * HourMeterSyncController, dossiê mobile, apontamento manual desktop, O.S.,
 * checklist) cai na mesma tabela horimeter_readings, então esta é a visão
 * unificada. Só leitura, mesmo padrão de ActivityLogResource/NotificationLogResource.
 */
class HorimeterReadingResource extends Resource
{
    protected static ?string $model = HorimeterReading::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Monitor de Horímetro';

    protected static ?string $modelLabel = 'Apontamento de Horímetro';

    protected static ?string $pluralModelLabel = 'Monitor de Horímetro';

    protected static ?int $navigationSort = 23;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('viewAny', HorimeterReading::class);
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
        return parent::getEloquentQuery()->with(['asset.client', 'recordedBy']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('asset.name')->label('Equipamento')->disabled(),
            Forms\Components\TextInput::make('asset.client.name')->label('Cliente')->disabled()->default('—'),
            Forms\Components\TextInput::make('reading')->label('Horímetro')->disabled(),
            Forms\Components\TextInput::make('recordedBy.name')->label('Registrado Por')->disabled(),
            Forms\Components\TextInput::make('source')
                ->label('Origem')
                ->formatStateUsing(fn (?HorimeterReading $record) => $record?->originLabel())
                ->disabled(),
            Forms\Components\TextInput::make('recorded_at')->label('Data/Hora')->disabled(),
            Forms\Components\Textarea::make('notes')->label('Observações')->disabled(),
            Forms\Components\Placeholder::make('photo')
                ->label('Foto')
                ->content(fn (?HorimeterReading $record) => $record?->photo_path
                    ? new HtmlString('<img src="'.Storage::disk('public')->url($record->photo_path).'" style="max-width: 300px; border-radius: 8px;">')
                    : 'Sem foto anexada'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('recorded_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Técnico / Usuário')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Equipamento')
                    ->searchable()
                    ->description(fn (HorimeterReading $record) => $record->asset?->patrimonio ? "PAT: {$record->asset->patrimonio}" : null),

                Tables\Columns\TextColumn::make('asset.client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('reading')
                    ->label('Horímetro')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.').'h')
                    ->sortable(),

                Tables\Columns\TextColumn::make('source')
                    ->label('Origem')
                    ->badge()
                    ->formatStateUsing(fn (HorimeterReading $record) => $record->originLabel())
                    ->color(fn (HorimeterReading $record) => $record->isFieldSource() ? 'warning' : 'gray'),

                Tables\Columns\IconColumn::make('photo_path')
                    ->label('Foto')
                    ->boolean()
                    ->trueIcon('heroicon-o-camera')
                    ->falseIcon('heroicon-o-no-symbol')
                    ->url(fn (HorimeterReading $record) => $record->photo_path ? Storage::disk('public')->url($record->photo_path) : null)
                    ->openUrlInNewTab(),

                Tables\Columns\IconColumn::make('reset_confirmed')
                    ->label('Reset Confirmado')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client')
                    ->label('Cliente')
                    ->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $q, $clientId) => $q->whereHas('asset', fn (Builder $qq) => $qq->where('client_id', $clientId))
                        );
                    }),

                Tables\Filters\SelectFilter::make('asset_id')
                    ->label('Equipamento')
                    ->options(fn () => Asset::query()->orderBy('name')->pluck('name', 'id')),

                Tables\Filters\SelectFilter::make('recorded_by')
                    ->label('Técnico / Usuário')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')),

                Tables\Filters\SelectFilter::make('source')
                    ->label('Tipo (Interno/Externo)')
                    ->options([
                        HorimeterReading::SOURCE_MOBILE_SYNC => 'Externo (Campo)',
                        HorimeterReading::SOURCE_MANUAL => 'Interno — Manual',
                        HorimeterReading::SOURCE_MAINTENANCE_ORDER => 'Interno — Ordem de Serviço',
                        HorimeterReading::SOURCE_CHECKLIST => 'Interno — Checklist',
                    ]),

                Tables\Filters\Filter::make('periodo')
                    ->label('Intervalo de Data')
                    ->form([
                        Forms\Components\DatePicker::make('de')->label('De'),
                        Forms\Components\DatePicker::make('ate')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['de'] ?? null, fn (Builder $q, $date) => $q->whereDate('recorded_at', '>=', $date))
                            ->when($data['ate'] ?? null, fn (Builder $q, $date) => $q->whereDate('recorded_at', '<=', $date));
                    }),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHorimeterReadings::route('/'),
        ];
    }
}
