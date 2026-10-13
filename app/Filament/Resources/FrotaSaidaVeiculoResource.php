<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaSaidaVeiculoResource\Pages;
use App\Models\Asset;
use App\Models\Client;
use App\Models\FleetDriver;
use App\Models\FrotaPercurso;
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
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    $ativo = $state ? Asset::find($state) : null;
                    $set('odometro', $ativo ? (int) floor((float) $ativo->odometro_atual) : null);
                    // O veículo sai de onde está (empresa, ou o cliente onde chegou no último trecho).
                    $local = $ativo?->localAtual();
                    $set('origem_tipo', $local['tipo'] ?? 'empresa');
                    $set('origem_cliente_id', $local['cliente_id'] ?? null);
                    $set('origem', $local['texto'] ?? null);
                    $set('destino_tipo', ($local['tipo'] ?? 'empresa') === 'empresa' ? 'cliente' : 'empresa');
                }),
            Forms\Components\Select::make('finalidade')->label('Finalidade')->options(FrotaSaidaVeiculo::finalidadeLabels())->required()->native(false),
            Forms\Components\Select::make('motorista_id')->label('Motorista')->required()->searchable()->native(false)
                ->options(fn () => FleetDriver::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->helperText('Só motoristas cadastrados em Logística → Frota → Motoristas.'),
            Forms\Components\DateTimePicker::make('saida_em')->label('Data e hora da saída')->seconds(false)->default(now())->maxDate(now()->addMinutes(5))->required(),
            Forms\Components\Section::make('Trecho')->description('De onde o veículo sai e para onde vai. Pode ser da empresa para o cliente, do cliente para a empresa ou entre clientes.')->columns(2)->columnSpanFull()->schema([
                Forms\Components\Select::make('origem_tipo')->label('Saindo de')->options(FrotaPercurso::TIPOS)->default('empresa')->required()->native(false)->live(),
                Forms\Components\Select::make('destino_tipo')->label('Indo para')->options(FrotaPercurso::TIPOS)->default('cliente')->required()->native(false)->live(),
                Forms\Components\Select::make('origem_cliente_id')->label('Cliente de origem')->searchable()->native(false)->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->visible(fn (Forms\Get $get) => $get('origem_tipo') === 'cliente')->required(fn (Forms\Get $get) => $get('origem_tipo') === 'cliente'),
                Forms\Components\Select::make('cliente_id')->label('Cliente de destino')->searchable()->native(false)->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->visible(fn (Forms\Get $get) => $get('destino_tipo') === 'cliente')->required(fn (Forms\Get $get) => $get('destino_tipo') === 'cliente'),
                Forms\Components\TextInput::make('origem')->label('Local de origem')->maxLength(191)->visible(fn (Forms\Get $get) => $get('origem_tipo') === 'outro')->required(fn (Forms\Get $get) => $get('origem_tipo') === 'outro'),
                Forms\Components\TextInput::make('destino')->label('Local de destino')->maxLength(191)->visible(fn (Forms\Get $get) => $get('destino_tipo') === 'outro')->required(fn (Forms\Get $get) => $get('destino_tipo') === 'outro'),
                Forms\Components\Select::make('percurso_id')->label('Percurso (recebe o km da viagem)')->searchable()->native(false)->columnSpanFull()
                    ->options(fn () => FrotaPercurso::query()->where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->all())
                    ->helperText('Deixe vazio: se só existir um percurso entre os dois locais, ele é escolhido sozinho.'),
            ]),
            Forms\Components\Textarea::make('motivo')->label('Motivo da saída')->required()->rows(2)->columnSpanFull(),
            Forms\Components\Select::make('equipamento_id')->label('Equipamento levado (opcional)')->searchable()->native(false)->columnSpanFull()
                ->getSearchResultsUsing(fn (string $busca) => Asset::search($busca)->reject(fn (Asset $a) => $a->isVehicle())->mapWithKeys(fn (Asset $a) => [$a->id => $a->name.' — '.($a->patrimonio ?? $a->placa ?? 's/ id')])->all())
                ->getOptionLabelUsing(fn ($valor) => Asset::find($valor)?->name)
                ->helperText('Se o veículo vai levar um equipamento (guindaste, empilhadeira...), busque por patrimônio, placa ou nome.'),
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['veiculo', 'motorista', 'cliente', 'equipamento', 'percurso']))
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
                Tables\Columns\TextColumn::make('trecho')->label('Trecho')->state(fn (FrotaSaidaVeiculo $r) => $r->trecho())->description(fn (FrotaSaidaVeiculo $r) => $r->percurso?->nome ? 'Percurso: '.$r->percurso->nome : null)->searchable(['origem', 'destino']),
                Tables\Columns\TextColumn::make('equipamento.name')->label('Equipamento levado')->placeholder('—')->description(fn (FrotaSaidaVeiculo $r) => $r->equipamento?->patrimonio)->toggleable(),
                Tables\Columns\TextColumn::make('motivo')->label('Motivo')->limit(40)->tooltip(fn (FrotaSaidaVeiculo $r) => $r->motivo)->toggleable(),
                Tables\Columns\TextColumn::make('saida_em')->label('Saída')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('retorno_em')->label('Entrada')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('odometro_saida')->label('Km saída')->numeric(thousandsSeparator: '.'),
                Tables\Columns\TextColumn::make('km')->label('Km da viagem')->placeholder('—')->suffix(' km')->state(fn (FrotaSaidaVeiculo $r) => $r->kmRodado()),
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
