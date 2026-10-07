<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaVinculoGpsResource\Pages;
use App\Models\Asset;
use App\Models\FrotaVinculoGps;
use App\Services\Frota\GpsOdometroService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** GPS (Traccar) dos veículos: a distância percorrida é somada ao odômetro de hora em hora. */
class FrotaVinculoGpsResource extends BaseResource
{
    protected static ?string $model = FrotaVinculoGps::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'GPS dos veículos';

    protected static ?int $navigationSort = 25;

    protected static ?string $modelLabel = 'GPS de veículo';

    protected static ?string $pluralModelLabel = 'GPS dos veículos';

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with('veiculo'))
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('vincular')->label('Vincular GPS')->icon('heroicon-o-plus')->color('success')
                    ->modalDescription('Informe o identificador (IMEI) do rastreador, o mesmo cadastrado no Traccar. O rastreador precisa já ter enviado ao menos uma posição. A contagem de km começa na hora do vínculo.')
                    ->form([
                        Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->native(false)->options(fn () => Asset::opcoesVeiculos()),
                        Forms\Components\TextInput::make('identificador')->label('Identificador do rastreador (IMEI)')->required()->maxLength(100),
                    ])
                    ->action(fn (array $data) => self::executar(fn () => app(GpsOdometroService::class)->vincular(Asset::findOrFail($data['ativo_id']), $data['identificador'], auth()->user()), 'GPS vinculado')),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('situacao')->label('Situação')->badge()
                    ->state(fn (FrotaVinculoGps $r) => ! $r->ativo ? 'Desvinculado' : (GpsOdometroService::horasSemSincronizar($r) !== null ? 'Atrasado' : 'Em dia'))
                    ->color(fn (string $state) => match ($state) {
                        'Em dia' => 'success', 'Atrasado' => 'warning', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Placa')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('identificador')->label('Rastreador')->searchable(),
                Tables\Columns\TextColumn::make('ultima_sincronizacao')->label('Última sincronização')->dateTime('d/m/Y H:i')->placeholder('Nunca'),
                Tables\Columns\TextColumn::make('ultimo_resultado')->label('Último resultado')->placeholder('—')->wrap(),
                Tables\Columns\TextColumn::make('odometro')->label('Odômetro')->numeric(thousandsSeparator: '.')->suffix(' km')
                    ->state(fn (FrotaVinculoGps $r) => (int) floor((float) $r->veiculo?->odometro_atual)),
            ])
            ->filters([
                Tables\Filters\Filter::make('ativos')->label('Só vínculos ativos')->toggle()->default()->query(fn (Builder $q) => $q->where('ativo', true)),
                Tables\Filters\SelectFilter::make('ativo_id')->label('Veículo')->options(fn () => Asset::opcoesVeiculos())->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('sincronizar')->label('Sincronizar agora')->icon('heroicon-o-arrow-path')->color('primary')
                    ->visible(fn (FrotaVinculoGps $r) => $r->ativo)
                    ->action(function (FrotaVinculoGps $r) {
                        $x = app(GpsOdometroService::class)->sincronizar($r);
                        Notification::make()->title($x['mensagem'])->{$x['status'] === 'falha' ? 'danger' : 'success'}()->send();
                    }),
                Tables\Actions\Action::make('desvincular')->label('Desvincular')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()
                    ->modalDescription('O odômetro deixa de ser atualizado pelo GPS. O que já foi somado fica no histórico.')
                    ->visible(fn (FrotaVinculoGps $r) => $r->ativo)
                    ->action(fn (FrotaVinculoGps $r) => self::executar(fn () => app(GpsOdometroService::class)->desvincular($r), 'GPS desvinculado')),
            ])
            ->emptyStateHeading('Nenhum GPS vinculado')
            ->emptyStateDescription('Use "Vincular GPS" para o odômetro do veículo acompanhar o rastreador.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFrotaVinculosGps::route('/')];
    }
}
