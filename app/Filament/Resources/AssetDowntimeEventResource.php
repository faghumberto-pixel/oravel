<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetDowntimeEventResource\Pages;
use App\Models\Asset;
use App\Models\AssetDowntimeEvent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Paradas abertas automaticamente por OS corretiva (ver MaintenanceOrder::booted())
 * aparecem aqui já com maintenance_order_id preenchido e fecham sozinhas
 * quando a OS conclui. "Registrar Parada" (ação do cabeçalho) é só pra
 * paradas SEM O.S. associada -- quebra constatada antes de abrir OS,
 * ociosidade, etc.
 */
class AssetDowntimeEventResource extends Resource
{
    protected static ?string $model = AssetDowntimeEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-pause-circle';

    protected static ?string $navigationGroup = 'Operação';

    protected static ?string $navigationLabel = 'Histórico de Paradas';

    protected static ?string $modelLabel = 'Parada';

    protected static ?string $pluralModelLabel = 'Paradas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('asset_id')
                ->label('Ativo')
                ->options(fn () => Asset::selectOptions())
                ->searchable()
                ->required(),
            Forms\Components\Select::make('reason')
                ->label('Motivo')
                ->options(AssetDowntimeEvent::reasonLabels())
                ->required(),
            Forms\Components\DateTimePicker::make('started_at')
                ->label('Início da parada')
                ->default(now())
                ->required(),
            Forms\Components\Textarea::make('notes')
                ->label('Observações')
                ->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('asset.patrimonio')->label('Patrimônio')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('asset.name')->label('Ativo')->searchable(),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => AssetDowntimeEvent::reasonLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('started_at')->label('Início')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('ended_at')->label('Fim')->dateTime('d/m/Y H:i')->placeholder('Em aberto')->sortable(),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Duração')
                    ->state(fn (AssetDowntimeEvent $record) => floor($record->duration / 60).'h '.($record->duration % 60).'min'),
                Tables\Columns\TextColumn::make('maintenanceOrder.os_number')->label('OS')->placeholder('—'),
                Tables\Columns\TextColumn::make('registeredBy.name')->label('Registrado por')->placeholder('—'),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('open')
                    ->label('Em aberto')
                    ->queries(
                        true: fn ($query) => $query->open(),
                        false: fn ($query) => $query->whereNotNull('ended_at'),
                    ),
                Tables\Filters\SelectFilter::make('reason')
                    ->label('Motivo')
                    ->options(AssetDowntimeEvent::reasonLabels()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Registrar Parada')
                    ->modalHeading('Registrar Parada Manual')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['registered_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('encerrar')
                    ->label('Encerrar Parada')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (AssetDowntimeEvent $record) => $record->ended_at === null)
                    ->requiresConfirmation()
                    ->action(function (AssetDowntimeEvent $record) {
                        $record->close();

                        Notification::make()
                            ->title('Parada encerrada')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAssetDowntimeEvents::route('/'),
        ];
    }
}
