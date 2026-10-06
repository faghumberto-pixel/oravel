<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaChecklistResource\Pages;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaChecklist;
use App\Services\Frota\ChecklistFrotaService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Histórico dos checklists de saída e retorno da frota (somente leitura; o preenchimento é pelo celular). */
class FrotaChecklistResource extends BaseResource
{
    protected static ?string $model = FrotaChecklist::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Checklists';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Checklist';

    protected static ?string $pluralModelLabel = 'Checklists da Frota';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function situacaoCor(?string $situacao): string
    {
        return match ($situacao) {
            FrotaChecklist::OK => 'success',
            FrotaChecklist::ATENCAO => 'warning',
            FrotaChecklist::BLOQUEADO => 'danger',
            FrotaChecklist::LIBERADO => 'info',
            default => 'gray',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['ativo', 'motorista', 'preenchidoPor']))
            ->defaultSort('concluido_em', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('concluido_em')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('ativo.placa')->label('Placa')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('ativo.name')->label('Veículo')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('motorista.name')->label('Motorista')->placeholder('—'),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn (string $state) => FrotaChecklist::tipoLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('situacao')->label('Situação')->badge()
                    ->formatStateUsing(fn (string $state) => FrotaChecklist::situacaoLabels()[$state] ?? $state)
                    ->color(fn (string $state) => self::situacaoCor($state)),
                Tables\Columns\TextColumn::make('odometro')->label('Odômetro')->suffix(' km')->numeric(thousandsSeparator: '.'),
                Tables\Columns\TextColumn::make('preenchidoPor.name')->label('Preenchido por')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ativo_id')->label('Veículo')
                    ->options(fn () => Asset::query()->where('grupo', Asset::GRUPO_VEICULO)->orderBy('placa')->get()->mapWithKeys(fn (Asset $a) => [$a->id => trim(($a->placa ? $a->placa.' — ' : '').$a->name)])->all())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('motorista_id')->label('Motorista')
                    ->options(fn () => FleetDriver::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('situacao')->label('Situação')->options(FrotaChecklist::situacaoLabels()),
                Tables\Filters\SelectFilter::make('tipo')->label('Tipo')->options(FrotaChecklist::tipoLabels()),
                Tables\Filters\Filter::make('periodo')->label('Período')
                    ->form([
                        Forms\Components\DatePicker::make('de')->label('De'),
                        Forms\Components\DatePicker::make('ate')->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['de'] ?? null, fn ($q, $d) => $q->whereDate('concluido_em', '>=', $d))
                        ->when($data['ate'] ?? null, fn ($q, $d) => $q->whereDate('concluido_em', '<=', $d))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Ver'),
                Tables\Actions\Action::make('liberar')
                    ->label('Liberar veículo')
                    ->icon('heroicon-o-lock-open')
                    ->color('danger')
                    ->visible(fn (FrotaChecklist $record) => $record->estaBloqueado() && Gate::allows('liberar', $record))
                    ->modalHeading('Liberar veículo bloqueado')
                    ->modalDescription('O veículo foi bloqueado por um item crítico com problema. Informe o motivo da liberação: ele fica registrado.')
                    ->form([Forms\Components\Textarea::make('motivo')->label('Motivo da liberação')->required()->rows(3)])
                    ->action(function (FrotaChecklist $record, array $data) {
                        try {
                            app(ChecklistFrotaService::class)->liberar($record, auth()->user(), $data['motivo']);
                            Notification::make()->title('Veículo liberado')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
            ])
            ->emptyStateHeading('Nenhum checklist ainda')
            ->emptyStateDescription('Os checklists são preenchidos pelo celular: abra o veículo pelo QR Code e toque em "Checklist de saída / retorno".');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFrotaChecklists::route('/'),
            'view' => Pages\ViewFrotaChecklist::route('/{record}'),
        ];
    }
}
