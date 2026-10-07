<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaAbastecimentoResource\Pages;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaAbastecimento;
use App\Services\Frota\AbastecimentoService;
use App\Services\Frota\EstoqueFrotaService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Abastecimentos da frota: litros, valor, km, consumo (km/l) e desvio. */
class FrotaAbastecimentoResource extends BaseResource
{
    protected static ?string $model = FrotaAbastecimento::class;

    protected static ?string $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Abastecimentos';

    protected static ?int $navigationSort = 11;

    protected static ?string $modelLabel = 'Abastecimento';

    protected static ?string $pluralModelLabel = 'Abastecimentos';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    /** @return array<int, Forms\Components\Component> */
    private static function camposRegistro(): array
    {
        return [
            Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->native(false)->live()
                ->options(fn () => Asset::opcoesVeiculos())
                ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('odometro', $state ? (int) floor((float) Asset::find($state)?->odometro_atual) : null)),
            Forms\Components\DateTimePicker::make('abastecido_em')->label('Data e hora')->seconds(false)->default(now())->maxDate(now()->addMinutes(5))->required(),
            Forms\Components\Select::make('motorista_id')->label('Motorista')->searchable()->native(false)
                ->options(fn () => FleetDriver::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all()),
            Forms\Components\TextInput::make('odometro')->label('Odômetro (km)')->numeric()->required(),
            Forms\Components\Select::make('combustivel')->label('Combustível')->options(FrotaAbastecimento::combustivelLabels())->required()->native(false),
            Forms\Components\TextInput::make('litros')->label('Litros')->numeric()->required()->minValue(0.01)->step(0.01),
            Forms\Components\TextInput::make('valor_litro')->label('Valor por litro (R$)')->numeric()->minValue(0)->prefix('R$')->step(0.001),
            Forms\Components\TextInput::make('valor_total')->label('Valor total (R$)')->numeric()->minValue(0)->prefix('R$')
                ->helperText('Informe o total ou o valor por litro; o outro é calculado.'),
            Forms\Components\Toggle::make('tanque_cheio')->label('Completou o tanque')->default(true)
                ->helperText('O consumo (km/l) só é calculado entre dois tanques cheios.'),
            Forms\Components\Select::make('origem')->label('Origem')->options(FrotaAbastecimento::origemLabels())->default('externo')->required()->native(false)->live(),
            Forms\Components\TextInput::make('posto')->label('Posto')->maxLength(191),
            Forms\Components\TextInput::make('nota_fiscal')->label('Nota fiscal')->maxLength(60),
            Forms\Components\Select::make('peca_id')->label('Combustível no estoque')->searchable()->native(false)
                ->visible(fn (Forms\Get $get) => $get('origem') === 'tanque_proprio')
                ->options(fn () => EstoqueFrotaService::opcoesPecas())->helperText('Se escolher, os litros saem do almoxarifado.'),
            Forms\Components\Select::make('almoxarifado_id')->label('Almoxarifado')->searchable()->native(false)
                ->visible(fn (Forms\Get $get) => $get('origem') === 'tanque_proprio')->required(fn (Forms\Get $get) => filled($get('peca_id')))
                ->options(fn () => EstoqueFrotaService::opcoesAlmoxarifados()),
            Forms\Components\TextInput::make('justificativa_odometro')->label('Justificativa (só se o km for menor que o último)')->maxLength(191),
            Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2)->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['veiculo', 'motorista']))
            ->defaultSort('abastecido_em', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('registrar_abastecimento')->label('Registrar abastecimento')->icon('heroicon-o-plus')->color('success')
                    ->form(self::camposRegistro())->modalWidth('4xl')
                    ->action(function (array $data) {
                        try {
                            $a = app(AbastecimentoService::class)->registrar(Asset::findOrFail($data['ativo_id']), $data, auth()->user());
                            $d = AbastecimentoService::desvio($a);
                            $msg = 'Abastecimento registrado'.($a->consumo_km_l ? ' — '.number_format((float) $a->consumo_km_l, 2, ',', '.').' km/l' : '');
                            Notification::make()->title($msg)->{in_array($d['situacao'], ['atencao', 'critica']) ? 'warning' : 'success'}()
                                ->body(in_array($d['situacao'], ['atencao', 'critica']) ? 'Consumo abaixo do habitual (média '.number_format($d['media'], 2, ',', '.').' km/l). Verifique o veículo.' : null)->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('abastecido_em')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Placa')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('combustivel')->label('Combustível')->formatStateUsing(fn (string $state) => FrotaAbastecimento::combustivelLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('litros')->label('Litros')->suffix(' L')->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total')->suffix(' L')),
                Tables\Columns\TextColumn::make('valor_total')->label('Valor')->money('BRL')->summarize(Tables\Columns\Summarizers\Sum::make()->money('BRL')->label('Total')),
                Tables\Columns\TextColumn::make('odometro')->label('Km')->numeric(thousandsSeparator: '.'),
                Tables\Columns\TextColumn::make('consumo_km_l')->label('km/l')->placeholder('—')->badge()
                    ->color(fn (FrotaAbastecimento $r) => match (AbastecimentoService::desvio($r)['situacao']) {
                        'critica' => 'danger', 'atencao' => 'warning', 'normal' => 'success', default => 'gray',
                    })
                    ->tooltip(fn (FrotaAbastecimento $r) => ($d = AbastecimentoService::desvio($r))['media'] ? 'Média anterior: '.number_format($d['media'], 2, ',', '.').' km/l' : 'Sem base de comparação ainda'),
                Tables\Columns\TextColumn::make('motorista.name')->label('Motorista')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('origem')->label('Origem')->formatStateUsing(fn (string $state) => FrotaAbastecimento::origemLabels()[$state] ?? $state)->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('posto')->label('Posto')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ativo_id')->label('Veículo')->options(fn () => Asset::opcoesVeiculos())->searchable(),
                Tables\Filters\SelectFilter::make('combustivel')->label('Combustível')->options(FrotaAbastecimento::combustivelLabels()),
                Tables\Filters\SelectFilter::make('origem')->label('Origem')->options(FrotaAbastecimento::origemLabels()),
            ])
            ->emptyStateHeading('Nenhum abastecimento registrado')
            ->emptyStateDescription('Use "Registrar abastecimento". O consumo (km/l) aparece a partir do segundo tanque cheio.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFrotaAbastecimentos::route('/')];
    }
}
