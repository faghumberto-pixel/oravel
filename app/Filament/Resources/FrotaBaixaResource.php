<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaBaixaResource\Pages;
use App\Models\Asset;
use App\Models\FrotaBaixa;
use App\Services\Frota\BaixaVeiculoService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Baixa de veículos (venda, sucata, perda total, doação). O veículo baixado sai das listas e das pendências. */
class FrotaBaixaResource extends BaseResource
{
    protected static ?string $model = FrotaBaixa::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-x-mark';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Baixa de veículos';

    protected static ?int $navigationSort = 24;

    protected static ?string $modelLabel = 'Baixa de veículo';

    protected static ?string $pluralModelLabel = 'Baixa de veículos';

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
            ->defaultSort('data', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('registrar_baixa')->label('Dar baixa em veículo')->icon('heroicon-o-plus')->color('danger')
                    ->modalDescription('O veículo sai das listas de escolha e das pendências, o motorista titular é encerrado e as chaves são desativadas. Não dá baixa com o veículo fora ou com chave retirada. O histórico (custos, multas, abastecimentos) é mantido.')
                    ->form([
                        Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->native(false)->options(fn () => Asset::opcoesVeiculos()),
                        Forms\Components\Select::make('tipo')->label('Tipo')->options(FrotaBaixa::tipoLabels())->required()->native(false)->live(),
                        Forms\Components\DatePicker::make('data')->label('Data')->default(now())->maxDate(now())->required(),
                        Forms\Components\TextInput::make('valor')->label('Valor da venda (R$)')->numeric()->minValue(0)->prefix('R$')->required(fn (Forms\Get $get) => $get('tipo') === 'venda'),
                        Forms\Components\TextInput::make('comprador')->label('Comprador / destino')->maxLength(191),
                        Forms\Components\TextInput::make('documento')->label('Documento (nota, recibo, CRV)')->maxLength(60),
                        Forms\Components\TextInput::make('odometro_final')->label('Odômetro final (km)')->numeric()->integer()->minValue(0),
                        Forms\Components\TextInput::make('justificativa_odometro')->label('Justificativa (só se o km for menor que o último)')->maxLength(191),
                        Forms\Components\Textarea::make('motivo')->label('Motivo da baixa')->required()->rows(2)->columnSpanFull(),
                    ])->modalWidth('3xl')
                    ->action(fn (array $data) => self::executar(fn () => app(BaixaVeiculoService::class)->registrar(Asset::findOrFail($data['ativo_id']), $data, auth()->user()), 'Baixa registrada')),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('situacao')->label('Situação')->badge()->state(fn (FrotaBaixa $r) => $r->vigente() ? 'Baixado' : 'Revertida')->color(fn (string $state) => $state === 'Baixado' ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Placa')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->formatStateUsing(fn (string $state) => FrotaBaixa::tipoLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('data')->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('valor')->label('Valor')->money('BRL')->placeholder('—'),
                Tables\Columns\TextColumn::make('resultado')->label('Venda − aquisição')->placeholder('—')->money('BRL')
                    ->state(fn (FrotaBaixa $r) => $r->tipo === 'venda' && $r->valor !== null && $r->veiculo?->acquisition_value ? (float) $r->valor - (float) $r->veiculo->acquisition_value : null),
                Tables\Columns\TextColumn::make('comprador')->label('Comprador / destino')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('motivo')->label('Motivo')->limit(40)->tooltip(fn (FrotaBaixa $r) => $r->motivo)->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('vigentes')->label('Só baixas vigentes')->toggle()->default()->query(fn (Builder $q) => $q->whereNull('revertida_em')),
                Tables\Filters\SelectFilter::make('tipo')->label('Tipo')->options(FrotaBaixa::tipoLabels()),
            ])
            ->actions([
                Tables\Actions\Action::make('reverter')->label('Reverter baixa')->icon('heroicon-o-arrow-uturn-left')->color('warning')
                    ->visible(fn (FrotaBaixa $r) => $r->vigente())
                    ->modalDescription('O veículo volta às listas e às pendências. O titular e as chaves NÃO voltam sozinhos: cadastre de novo se precisar.')
                    ->form([Forms\Components\Textarea::make('motivo')->label('Motivo da reversão')->required()->rows(2)])
                    ->action(fn (FrotaBaixa $r, array $data) => self::executar(fn () => app(BaixaVeiculoService::class)->reverter($r, $data['motivo']), 'Baixa revertida')),
            ])
            ->emptyStateHeading('Nenhuma baixa registrada');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFrotaBaixas::route('/')];
    }
}
