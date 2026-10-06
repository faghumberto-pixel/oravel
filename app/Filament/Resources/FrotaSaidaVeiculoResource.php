<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaSaidaVeiculoResource\Pages;
use App\Models\Asset;
use App\Models\Client;
use App\Models\FleetDriver;
use App\Models\FrotaSaidaVeiculo;
use App\Services\Frota\SaidaVeiculoService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Entrada e saída de veículos da frota (visita técnica, administrativo, diretoria, cliente, locação, manutenção...). */
class FrotaSaidaVeiculoResource extends BaseResource
{
    protected static ?string $model = FrotaSaidaVeiculo::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationLabel = 'Entrada e saída de veículos';

    protected static ?int $navigationSort = 0;

    protected static ?string $modelLabel = 'Saída de veículo';

    protected static ?string $pluralModelLabel = 'Entrada e saída de veículos';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canViewAny()) {
            return null;
        }
        $n = FrotaSaidaVeiculo::fora()->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Veículos fora agora';
    }

    /** @return array<int, Forms\Components\Component> */
    private static function camposSaida(): array
    {
        return [
            Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->native(false)->live()
                ->options(fn () => Asset::opcoesVeiculos())
                ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('odometro', $state ? (int) floor((float) Asset::find($state)?->odometro_atual) : null)),
            Forms\Components\Select::make('finalidade')->label('Finalidade')->options(FrotaSaidaVeiculo::finalidadeLabels())->required()->native(false),
            Forms\Components\Select::make('motorista_id')->label('Motorista')->searchable()->native(false)
                ->options(fn () => FleetDriver::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->helperText('Ou escreva o nome abaixo, se não for motorista cadastrado.'),
            Forms\Components\TextInput::make('condutor_nome')->label('Nome de quem levou (se não cadastrado)')->maxLength(191),
            Forms\Components\DateTimePicker::make('saida_em')->label('Data e hora da saída')->seconds(false)->default(now())->maxDate(now()->addMinutes(5))->required(),
            Forms\Components\TextInput::make('destino')->label('Destino')->required()->maxLength(191),
            Forms\Components\Textarea::make('motivo')->label('Motivo da saída')->required()->rows(2)->columnSpanFull(),
            Forms\Components\Select::make('cliente_id')->label('Cliente (opcional)')->searchable()->native(false)
                ->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id')->all()),
            Forms\Components\TextInput::make('odometro')->label('Odômetro na saída (km)')->numeric()->required(),
            Forms\Components\TextInput::make('justificativa_odometro')->label('Justificativa (só se o km for menor que o último)')->maxLength(191),
            Forms\Components\Select::make('combustivel')->label('Combustível')->native(false)
                ->options(['vazio' => 'Reserva', '1/4' => '1/4', '1/2' => '1/2', '3/4' => '3/4', 'cheio' => 'Cheio']),
            Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2)->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['veiculo', 'motorista', 'cliente']))
            ->defaultSort('saida_em', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('registrar_saida')->label('Registrar saída')->icon('heroicon-o-arrow-up-right')->color('success')
                    ->form(self::camposSaida())->modalWidth('3xl')
                    ->action(function (array $data) {
                        try {
                            $saida = app(SaidaVeiculoService::class)->registrarSaida(Asset::findOrFail($data['ativo_id']), $data, auth()->user());
                            Notification::make()->title('Saída registrada: '.($saida->veiculo?->placa ?? $saida->veiculo?->name))->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('situacao')->label('Situação')->badge()
                    ->state(fn (FrotaSaidaVeiculo $r) => $r->estaFora() ? 'Fora' : 'Devolvido')
                    ->color(fn (string $state) => $state === 'Fora' ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Placa')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('veiculo.name')->label('Veículo')->toggleable(),
                Tables\Columns\TextColumn::make('finalidade')->label('Finalidade')->formatStateUsing(fn (string $state) => FrotaSaidaVeiculo::finalidadeLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('condutor')->label('Quem levou')->state(fn (FrotaSaidaVeiculo $r) => $r->condutor()),
                Tables\Columns\TextColumn::make('destino')->label('Destino')->description(fn (FrotaSaidaVeiculo $r) => $r->cliente?->name)->searchable(),
                Tables\Columns\TextColumn::make('motivo')->label('Motivo')->limit(40)->tooltip(fn (FrotaSaidaVeiculo $r) => $r->motivo)->toggleable(),
                Tables\Columns\TextColumn::make('saida_em')->label('Saída')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('retorno_em')->label('Entrada')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('odometro_saida')->label('Km saída')->numeric(thousandsSeparator: '.'),
                Tables\Columns\TextColumn::make('km')->label('Km rodado')->placeholder('—')->suffix(' km')->state(fn (FrotaSaidaVeiculo $r) => $r->kmRodado()),
            ])
            ->filters([
                Tables\Filters\Filter::make('fora')->label('Só veículos fora agora')->toggle()->query(fn (Builder $q) => $q->fora()),
                Tables\Filters\SelectFilter::make('ativo_id')->label('Veículo')->options(fn () => Asset::opcoesVeiculos())->searchable(),
                Tables\Filters\SelectFilter::make('finalidade')->label('Finalidade')->options(FrotaSaidaVeiculo::finalidadeLabels()),
            ])
            ->actions([
                Tables\Actions\Action::make('registrar_entrada')->label('Registrar entrada')->icon('heroicon-o-arrow-down-left')->color('primary')
                    ->visible(fn (FrotaSaidaVeiculo $r) => $r->estaFora())
                    ->form(fn (FrotaSaidaVeiculo $r) => [
                        Forms\Components\DateTimePicker::make('retorno_em')->label('Data e hora da entrada')->seconds(false)->default(now())->maxDate(now()->addMinutes(5))->required(),
                        Forms\Components\TextInput::make('odometro')->label('Odômetro na entrada (km)')->numeric()->required()->default((int) floor((float) $r->veiculo?->odometro_atual)),
                        Forms\Components\TextInput::make('justificativa_odometro')->label('Justificativa (só se o km for menor que o da saída)')->maxLength(191),
                        Forms\Components\Select::make('combustivel')->label('Combustível')->native(false)
                            ->options(['vazio' => 'Reserva', '1/4' => '1/4', '1/2' => '1/2', '3/4' => '3/4', 'cheio' => 'Cheio']),
                        Forms\Components\Textarea::make('observacoes')->label('Observações (avarias, ocorrências)')->rows(2),
                    ])
                    ->action(function (FrotaSaidaVeiculo $r, array $data) {
                        try {
                            app(SaidaVeiculoService::class)->registrarEntrada($r, $data, auth()->user());
                            Notification::make()->title('Entrada registrada')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
            ])
            ->emptyStateHeading('Nenhuma saída registrada')
            ->emptyStateDescription('Use "Registrar saída" quando um veículo sair e "Registrar entrada" quando voltar.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFrotaSaidasVeiculo::route('/')];
    }
}
