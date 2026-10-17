<?php

namespace App\Filament\Central\Resources\TenantResource\RelationManagers;

use App\Models\TenantComplemento;
use App\Services\ComplementoException;
use App\Services\ComplementoService;
use App\Support\Precos;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Complementos do cliente: cobrados na assinatura recorrente, com fidelidade de 12 meses. Gestão exclusiva da Oravel (Central). */
class ComplementosRelationManager extends RelationManager
{
    protected static string $relationship = 'complementos';

    protected static ?string $title = 'Complementos';

    private function executar(\Closure $acao, string $titulo, ?string $corpo = null): void
    {
        try {
            $acao();
            Notification::make()->title($titulo)->body($corpo)->success()->send();
        } catch (ComplementoException $e) {
            Notification::make()->title('Não foi possível')->body($e->getMessage())->danger()->persistent()->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes())
            ->defaultSort('contratado_em', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('feature')->label('Complemento')->formatStateUsing(fn ($state) => ComplementoService::catalogo()[$state] ?? $state)->weight('bold'),
                Tables\Columns\TextColumn::make('valor_cheio')->label('Valor cheio')->money('BRL')->placeholder('—'),
                Tables\Columns\TextColumn::make('valor_mensal')->label('Cobrado por mês')->money('BRL')->description(fn (TenantComplemento $r) => '-'.Precos::descontoPercentual().'% pela recorrência'),
                Tables\Columns\TextColumn::make('contratado_em')->label('Desde')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('fidelidade_ate')->label('Fidelidade até')->date('d/m/Y')
                    ->description(fn (TenantComplemento $r) => $r->vigente() ? ($r->emFidelidade() ? 'Em fidelidade' : 'Fidelidade cumprida') : null),
                Tables\Columns\TextColumn::make('status')->label('Situação')->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        TenantComplemento::ATIVO => 'Ativo', TenantComplemento::ENCERRANDO => 'Cancelamento agendado', default => 'Encerrado'
                    })
                    ->color(fn ($state) => match ($state) {
                        TenantComplemento::ATIVO => 'success', TenantComplemento::ENCERRANDO => 'warning', default => 'gray'
                    })
                    ->description(fn (TenantComplemento $r) => $r->status === TenantComplemento::ENCERRANDO ? 'Vale em '.$r->encerra_em->format('d/m/Y') : ($r->encerramento_antecipado ? 'Encerrado antes da fidelidade: '.$r->motivo_encerramento : null)),
            ])
            ->headerActions([
                Tables\Actions\Action::make('contratar')
                    ->label('Contratar complemento')
                    ->icon('heroicon-o-plus-circle')
                    ->form([
                        Forms\Components\Select::make('feature')->label('Complemento')->native(false)->required()
                            ->options(fn () => collect(ComplementoService::catalogo())->reject(fn ($n, $k) => $this->getOwnerRecord()->complementos()->withoutGlobalScopes()->where('feature', $k)->whereIn('status', [TenantComplemento::ATIVO, TenantComplemento::ENCERRANDO])->exists())->all()),
                        Forms\Components\TextInput::make('valor')->label('Valor cheio mensal (R$)')->numeric()->minValue(0.01)->required()->step(0.01)->live(onBlur: true),
                        Forms\Components\Placeholder::make('aviso')->label('')->content(fn (Forms\Get $get) => 'O cliente paga '.Precos::brl(Precos::recorrente((float) $get('valor'))).'/mês ('.Precos::descontoPercentual().'% de desconto pela recorrência), dentro da assinatura recorrente, com fidelidade de '.ComplementoService::fidelidadeMeses().' meses. O cliente não cancela antes disso.'),
                    ])
                    ->action(fn (array $data) => $this->executar(
                        fn () => app(ComplementoService::class)->contratar($this->getOwnerRecord(), $data['feature'], (float) $data['valor'], auth()->user()),
                        'Complemento contratado', 'O novo valor da mensalidade segue para o Asaas.',
                    )),
            ])
            ->actions([
                Tables\Actions\Action::make('cancelar')
                    ->label('Cancelar')->icon('heroicon-o-x-circle')->color('warning')
                    ->visible(fn (TenantComplemento $r) => $r->status === TenantComplemento::ATIVO)
                    ->requiresConfirmation()
                    ->modalDescription('Só vale depois da fidelidade e entra em vigor no próximo ciclo da assinatura.')
                    ->action(fn (TenantComplemento $r) => $this->executar(fn () => app(ComplementoService::class)->cancelar($r, auth()->user()), 'Cancelamento agendado')),
                Tables\Actions\Action::make('encerrarAntecipado')
                    ->label('Encerramento antecipado')->icon('heroicon-o-no-symbol')->color('danger')
                    ->visible(fn (TenantComplemento $r) => $r->vigente() && (bool) auth()->user()?->isSuperAdmin())
                    ->form([Forms\Components\Textarea::make('motivo')->label('Motivo (obrigatório)')->required()->rows(3)])
                    ->modalDescription('Encerra agora, antes ou depois da fidelidade, sem cobrar o saldo. Fica registrado no histórico do cliente.')
                    ->action(fn (TenantComplemento $r, array $data) => $this->executar(fn () => app(ComplementoService::class)->encerrarAntecipado($r, auth()->user(), $data['motivo']), 'Complemento encerrado')),
            ]);
    }
}
