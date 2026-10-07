<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaPlanoRevisaoResource\Pages;
use App\Models\Asset;
use App\Models\FrotaPlanoRevisao;
use App\Services\Frota\RevisaoService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Revisões preventivas por km e/ou tempo de cada veículo (freios, correia, filtros...). */
class FrotaPlanoRevisaoResource extends BaseResource
{
    protected static ?string $model = FrotaPlanoRevisao::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Revisões';

    protected static ?int $navigationSort = 15;

    protected static ?string $modelLabel = 'Item de revisão';

    protected static ?string $pluralModelLabel = 'Revisões por km e tempo';

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
            ->defaultSort('nome')
            ->headerActions([
                Tables\Actions\Action::make('novo_item')->label('Novo item de revisão')->icon('heroicon-o-plus')->color('success')
                    ->form([
                        Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->native(false)->options(fn () => Asset::opcoesVeiculos()),
                        Forms\Components\TextInput::make('nome')->label('Item')->required()->maxLength(191)->placeholder('Ex.: Freios'),
                        Forms\Components\TextInput::make('intervalo_km')->label('A cada (km)')->numeric()->integer()->minValue(1),
                        Forms\Components\TextInput::make('intervalo_dias')->label('Ou a cada (dias)')->numeric()->integer()->minValue(1),
                        Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2),
                    ])
                    ->action(fn (array $data) => self::executar(fn () => app(RevisaoService::class)->criarPlano(Asset::findOrFail($data['ativo_id']), $data), 'Item criado')),
                Tables\Actions\Action::make('aplicar_padrao')->label('Aplicar itens sugeridos')->icon('heroicon-o-sparkles')->color('gray')
                    ->modalDescription('Cria freios, filtros, correia, alinhamento e fluido de freio, com intervalos sugeridos que você pode ajustar depois. Só cria o que o veículo ainda não tem.')
                    ->form([Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->native(false)->options(fn () => Asset::opcoesVeiculos())])
                    ->action(function (array $data) {
                        try {
                            $n = app(RevisaoService::class)->aplicarPadrao(Asset::findOrFail($data['ativo_id']));
                            Notification::make()->title($n ? "{$n} item(ns) criado(s)" : 'O veículo já tem todos os itens sugeridos')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('situacao')->label('Situação')->badge()
                    ->state(fn (FrotaPlanoRevisao $r) => ['vencida' => 'Vencida', 'proxima' => 'Próxima', 'em_dia' => 'Em dia', 'sem_registro' => 'Sem registro'][RevisaoService::situacao($r)['situacao']])
                    ->color(fn (string $state) => match ($state) {
                        'Vencida' => 'danger', 'Próxima' => 'warning', 'Em dia' => 'success', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Placa')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('nome')->label('Item')->searchable(),
                Tables\Columns\TextColumn::make('intervalo')->label('A cada')->placeholder('—')
                    ->state(fn (FrotaPlanoRevisao $r) => collect([$r->intervalo_km ? number_format($r->intervalo_km, 0, ',', '.').' km' : null, $r->intervalo_dias ? $r->intervalo_dias.' dias' : null])->filter()->implode(' ou ') ?: null),
                Tables\Columns\TextColumn::make('ultima')->label('Última')->placeholder('—')
                    ->state(fn (FrotaPlanoRevisao $r) => ($u = $r->ultima()) ? $u->realizada_em->format('d/m/Y').' · '.number_format($u->odometro, 0, ',', '.').' km' : null),
                Tables\Columns\TextColumn::make('proxima')->label('Próxima')->placeholder('—')
                    ->state(function (FrotaPlanoRevisao $r) {
                        $s = RevisaoService::situacao($r);

                        return collect([$s['proxima_km'] ? number_format($s['proxima_km'], 0, ',', '.').' km' : null, $s['proxima_data'] ? date('d/m/Y', strtotime($s['proxima_data'])) : null])->filter()->implode(' ou ') ?: null;
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ativo_id')->label('Veículo')->options(fn () => Asset::opcoesVeiculos())->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('registrar')->label('Registrar revisão feita')->icon('heroicon-o-check-circle')->color('success')
                    ->form(fn (FrotaPlanoRevisao $r) => [
                        Forms\Components\DatePicker::make('realizada_em')->label('Data')->default(now())->maxDate(now())->required(),
                        Forms\Components\TextInput::make('odometro')->label('Odômetro (km)')->numeric()->required()->default((int) floor((float) $r->veiculo?->odometro_atual)),
                        Forms\Components\TextInput::make('custo')->label('Custo (R$, opcional)')->numeric()->minValue(0)->prefix('R$'),
                        Forms\Components\TextInput::make('justificativa_odometro')->label('Justificativa (só se o km for menor que o último)')->maxLength(191),
                        Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2),
                    ])
                    ->action(fn (FrotaPlanoRevisao $r, array $data) => self::executar(fn () => app(RevisaoService::class)->registrar($r, $data, auth()->user()), 'Revisão registrada')),
                Tables\Actions\Action::make('desativar')->label('Desativar')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()
                    ->modalDescription('O item deixa de aparecer e de gerar pendências. O histórico do que foi feito fica guardado.')
                    ->action(fn (FrotaPlanoRevisao $r) => self::executar(fn () => app(RevisaoService::class)->desativar($r), 'Item desativado')),
            ])
            ->emptyStateHeading('Nenhum item de revisão')
            ->emptyStateDescription('Use "Aplicar itens sugeridos" para começar com freios, filtros, correia e alinhamento.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFrotaPlanosRevisao::route('/')];
    }
}
