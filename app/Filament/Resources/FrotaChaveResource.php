<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaChaveResource\Pages;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaChave;
use App\Services\Frota\ChaveService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Chaves dos veículos: onde estão, com quem e desde quando; entrega e devolução com responsável. */
class FrotaChaveResource extends BaseResource
{
    protected static ?string $model = FrotaChave::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Chaves';

    protected static ?int $navigationSort = 19;

    protected static ?string $modelLabel = 'Chave';

    protected static ?string $pluralModelLabel = 'Chaves dos veículos';

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with('veiculo')->where('ativo', true))
            ->defaultSort('identificacao')
            ->headerActions([
                Tables\Actions\Action::make('nova_chave')->label('Nova chave')->icon('heroicon-o-plus')->color('success')
                    ->form([
                        Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->native(false)->options(fn () => Asset::opcoesVeiculos()),
                        Forms\Components\TextInput::make('identificacao')->label('Identificação')->required()->maxLength(191)->placeholder('Ex.: Chave principal, Reserva'),
                        Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2),
                    ])
                    ->action(fn (array $data) => self::executar(fn () => app(ChaveService::class)->criar(Asset::findOrFail($data['ativo_id']), $data['identificacao'], $data['observacoes'] ?? null), 'Chave cadastrada')),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('situacao')->label('Situação')->badge()
                    ->state(fn (FrotaChave $r) => ($e = $r->entregaAberta()) ? 'Com '.$e->responsavel() : 'Na base')
                    ->color(fn (string $state) => $state === 'Na base' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Placa')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('identificacao')->label('Chave')->searchable(),
                Tables\Columns\TextColumn::make('desde')->label('Retirada em')->placeholder('—')
                    ->state(fn (FrotaChave $r) => ($e = $r->entregaAberta()) ? $e->entregue_em->format('d/m/Y H:i').' — '.$e->motivo : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ativo_id')->label('Veículo')->options(fn () => Asset::opcoesVeiculos())->searchable(),
                Tables\Filters\Filter::make('fora')->label('Só chaves fora da base')->toggle()
                    ->query(fn (Builder $q) => $q->whereExists(fn ($s) => $s->selectRaw('1')->from('frota_entregas_chave')->whereColumn('frota_entregas_chave.chave_id', 'frota_chaves.id')->whereNull('devolvida_em'))),
            ])
            ->actions([
                Tables\Actions\Action::make('entregar')->label('Entregar')->icon('heroicon-o-arrow-up-right')->color('warning')
                    ->visible(fn (FrotaChave $r) => $r->entregaAberta() === null)
                    ->form([
                        Forms\Components\Select::make('motorista_id')->label('Motorista')->searchable()->native(false)
                            ->options(fn () => FleetDriver::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all()),
                        Forms\Components\TextInput::make('responsavel_nome')->label('Ou nome de quem retira (mecânico, terceiro...)')->maxLength(191),
                        Forms\Components\TextInput::make('motivo')->label('Motivo')->required()->maxLength(191),
                        Forms\Components\DateTimePicker::make('entregue_em')->label('Data e hora')->seconds(false)->default(now())->maxDate(now()->addMinutes(5))->required(),
                        Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2),
                    ])
                    ->action(fn (FrotaChave $r, array $data) => self::executar(fn () => app(ChaveService::class)->entregar($r, $data, auth()->user()), 'Chave entregue')),
                Tables\Actions\Action::make('devolver')->label('Devolver')->icon('heroicon-o-arrow-down-left')->color('success')
                    ->visible(fn (FrotaChave $r) => $r->entregaAberta() !== null)
                    ->form([
                        Forms\Components\DateTimePicker::make('devolvida_em')->label('Data e hora')->seconds(false)->default(now())->maxDate(now()->addMinutes(5))->required(),
                        Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2),
                    ])
                    ->action(fn (FrotaChave $r, array $data) => self::executar(fn () => app(ChaveService::class)->devolver($r->entregaAberta(), $data['devolvida_em'], $data['observacoes'] ?? null, auth()->user()), 'Chave devolvida')),
                Tables\Actions\Action::make('historico')->label('Histórico')->icon('heroicon-o-clock')->color('gray')
                    ->modalHeading(fn (FrotaChave $r) => 'Histórico — '.$r->identificacao)->modalSubmitAction(false)->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (FrotaChave $r) => view('filament.partials.historico-chave', ['entregas' => $r->entregas()->with('motorista')->limit(20)->get()])),
                Tables\Actions\Action::make('desativar')->label('Desativar')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()
                    ->action(fn (FrotaChave $r) => self::executar(fn () => app(ChaveService::class)->desativar($r), 'Chave desativada')),
            ])
            ->emptyStateHeading('Nenhuma chave cadastrada')
            ->emptyStateDescription('Use "Nova chave" e depois "Entregar" e "Devolver" para saber sempre com quem ela está.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFrotaChaves::route('/')];
    }
}
