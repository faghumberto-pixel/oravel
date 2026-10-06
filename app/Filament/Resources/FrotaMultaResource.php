<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaMultaResource\Pages;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaMulta;
use App\Services\Frota\MultaService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Multas de trânsito da frota: registro, condutor, pontos, pagamento e recurso. */
class FrotaMultaResource extends BaseResource
{
    protected static ?string $model = FrotaMulta::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Multas';

    protected static ?int $navigationSort = 9;

    protected static ?string $modelLabel = 'Multa';

    protected static ?string $pluralModelLabel = 'Multas';

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
        $n = FrotaMulta::emAberto()->count();

        return $n > 0 ? (string) $n : null;
    }

    /** @return array<int, string> */
    private static function motoristas(): array
    {
        return FleetDriver::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all();
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['veiculo', 'motorista']))
            ->defaultSort('vencimento')
            ->headerActions([
                Tables\Actions\Action::make('registrar_multa')->label('Registrar multa')->icon('heroicon-o-plus')->color('success')->modalWidth('3xl')
                    ->form([
                        Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->native(false)->options(fn () => Asset::opcoesVeiculos()),
                        Forms\Components\TextInput::make('numero_auto')->label('Nº do auto de infração')->required()->maxLength(60),
                        Forms\Components\DateTimePicker::make('infracao_em')->label('Data e hora da infração')->seconds(false)->required()->maxDate(now()->addMinutes(5))->default(now()),
                        Forms\Components\TextInput::make('local')->label('Local')->maxLength(191),
                        Forms\Components\TextInput::make('codigo_infracao')->label('Código da infração')->maxLength(20),
                        Forms\Components\Select::make('gravidade')->label('Gravidade')->options(FrotaMulta::gravidadeLabels())->required()->native(false)->live()
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('pontos', FrotaMulta::PONTOS[$state] ?? null)),
                        Forms\Components\TextInput::make('pontos')->label('Pontos')->numeric()->minValue(0)->maxValue(20),
                        Forms\Components\TextInput::make('valor')->label('Valor (R$)')->numeric()->required()->minValue(0.01)->prefix('R$'),
                        Forms\Components\DatePicker::make('vencimento')->label('Vencimento do pagamento')->required(),
                        Forms\Components\DatePicker::make('prazo_indicacao')->label('Prazo para indicar o condutor'),
                        Forms\Components\Select::make('motorista_id')->label('Motorista (deixe em branco para o sistema sugerir pela saída do veículo)')->searchable()->native(false)->options(fn () => self::motoristas()),
                        Forms\Components\Select::make('quem_paga')->label('Quem paga')->options(['empresa' => 'Empresa', 'motorista' => 'Motorista'])->default('empresa')->native(false),
                        Forms\Components\Textarea::make('descricao')->label('Descrição da infração')->required()->rows(2)->columnSpanFull(),
                        Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2)->columnSpanFull(),
                    ])
                    ->action(function (array $data) {
                        try {
                            $m = app(MultaService::class)->registrar(Asset::findOrFail($data['ativo_id']), $data, auth()->user());
                            Notification::make()->title('Multa registrada'.($m->motorista ? ' — condutor: '.$m->motorista->name : ' — indique o condutor'))->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('situacao')->label('Situação')->badge()->formatStateUsing(fn (string $state) => FrotaMulta::situacaoLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        FrotaMulta::PAGA => 'success', FrotaMulta::CANCELADA => 'gray', FrotaMulta::ABERTA => 'danger', default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('numero_auto')->label('Auto')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Placa')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('motorista.name')->label('Condutor')->placeholder('Não indicado')->searchable(),
                Tables\Columns\TextColumn::make('infracao_em')->label('Infração')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('descricao')->label('Descrição')->limit(40)->tooltip(fn (FrotaMulta $r) => $r->descricao)->toggleable(),
                Tables\Columns\TextColumn::make('gravidade')->label('Gravidade')->formatStateUsing(fn (string $state) => FrotaMulta::gravidadeLabels()[$state] ?? $state)->toggleable(),
                Tables\Columns\TextColumn::make('pontos')->label('Pontos'),
                Tables\Columns\TextColumn::make('valor')->label('Valor')->money('BRL')->summarize(Tables\Columns\Summarizers\Sum::make()->money('BRL')->label('Total')),
                Tables\Columns\TextColumn::make('vencimento')->label('Vence em')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('quem_paga')->label('Quem paga')->formatStateUsing(fn (string $state) => ucfirst($state))->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('em_aberto')->label('Só em aberto')->toggle()->default()->query(fn (Builder $q) => $q->whereIn('situacao', [FrotaMulta::ABERTA, FrotaMulta::CONDUTOR_INDICADO, FrotaMulta::RECORRIDA])),
                Tables\Filters\SelectFilter::make('ativo_id')->label('Veículo')->options(fn () => Asset::opcoesVeiculos())->searchable(),
                Tables\Filters\SelectFilter::make('motorista_id')->label('Condutor')->options(fn () => self::motoristas())->searchable(),
                Tables\Filters\SelectFilter::make('situacao')->label('Situação')->options(FrotaMulta::situacaoLabels()),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('indicar_condutor')->label('Indicar condutor')->icon('heroicon-o-user')
                        ->visible(fn (FrotaMulta $r) => ! $r->encerrada())
                        ->form([Forms\Components\Select::make('motorista_id')->label('Motorista')->required()->searchable()->native(false)->options(fn () => self::motoristas())])
                        ->action(fn (FrotaMulta $r, array $data) => self::executar(fn () => app(MultaService::class)->indicarCondutor($r, $data['motorista_id']), 'Condutor indicado')),
                    Tables\Actions\Action::make('pagar')->label('Registrar pagamento')->icon('heroicon-o-banknotes')->color('success')
                        ->visible(fn (FrotaMulta $r) => ! $r->encerrada())
                        ->form([Forms\Components\DatePicker::make('pago_em')->label('Data do pagamento')->default(now())->maxDate(now())->required()])
                        ->action(fn (FrotaMulta $r, array $data) => self::executar(fn () => app(MultaService::class)->pagar($r, $data['pago_em']), 'Pagamento registrado')),
                    Tables\Actions\Action::make('recorrer')->label('Marcar como recorrida')->icon('heroicon-o-scale')
                        ->visible(fn (FrotaMulta $r) => ! $r->encerrada() && $r->situacao !== FrotaMulta::RECORRIDA)->requiresConfirmation()
                        ->action(fn (FrotaMulta $r) => self::executar(fn () => app(MultaService::class)->recorrer($r), 'Multa marcada como recorrida')),
                    Tables\Actions\Action::make('cancelar')->label('Cancelar multa')->icon('heroicon-o-x-circle')->color('danger')
                        ->visible(fn (FrotaMulta $r) => ! $r->encerrada())
                        ->form([Forms\Components\Textarea::make('motivo')->label('Motivo do cancelamento')->required()->rows(2)])
                        ->action(fn (FrotaMulta $r, array $data) => self::executar(fn () => app(MultaService::class)->cancelar($r, $data['motivo']), 'Multa cancelada')),
                ]),
            ])
            ->emptyStateHeading('Nenhuma multa registrada')
            ->emptyStateDescription('Use "Registrar multa". Se o veículo estava em uma saída registrada, o condutor é sugerido sozinho.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFrotaMultas::route('/')];
    }
}
