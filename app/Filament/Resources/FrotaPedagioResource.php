<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaPedagioResource\Pages;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaPedagio;
use App\Services\Frota\PedagioService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Pedágios dos veículos: praça, valor, tag e condutor (sugerido pela saída do veículo). */
class FrotaPedagioResource extends BaseResource
{
    protected static ?string $model = FrotaPedagio::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Pedágios';

    protected static ?int $navigationSort = 22;

    protected static ?string $modelLabel = 'Pedágio';

    protected static ?string $pluralModelLabel = 'Pedágios';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    private static function executar(\Closure $acao, string $sucesso): void
    {
        try {
            $acao();
            Notification::make()->title($sucesso)->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['veiculo', 'motorista', 'tag']))
            ->defaultSort('passou_em', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('registrar_pedagio')->label('Registrar pedágio')->icon('heroicon-o-plus')->color('success')
                    ->form([
                        Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->native(false)->options(fn () => Asset::opcoesVeiculos()),
                        Forms\Components\DateTimePicker::make('passou_em')->label('Data e hora')->seconds(false)->default(now())->maxDate(now()->addMinutes(5))->required(),
                        Forms\Components\TextInput::make('local')->label('Pedágio (praça ou rodovia)')->required()->maxLength(191),
                        Forms\Components\TextInput::make('valor')->label('Valor (R$)')->numeric()->required()->minValue(0.01)->prefix('R$'),
                        Forms\Components\Select::make('motorista_id')->label('Motorista (em branco = sugerido pela saída do veículo)')->searchable()->native(false)
                            ->options(fn () => FleetDriver::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all()),
                        Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2),
                    ])
                    ->action(fn (array $data) => self::executar(fn () => app(PedagioService::class)->registrar(Asset::findOrFail($data['ativo_id']), $data, auth()->user()), 'Pedágio registrado')),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('passou_em')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Placa')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('local')->label('Pedágio')->searchable(),
                Tables\Columns\TextColumn::make('valor')->label('Valor')->money('BRL')->summarize(Tables\Columns\Summarizers\Sum::make()->money('BRL')->label('Total')),
                Tables\Columns\TextColumn::make('motorista.name')->label('Motorista')->placeholder('—'),
                Tables\Columns\TextColumn::make('tag.numero')->label('Tag')->placeholder('—')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ativo_id')->label('Veículo')->options(fn () => Asset::opcoesVeiculos())->searchable(),
                Tables\Filters\SelectFilter::make('motorista_id')->label('Motorista')->options(fn () => FleetDriver::query()->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            ])
            ->emptyStateHeading('Nenhum pedágio registrado');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFrotaPedagios::route('/')];
    }
}
