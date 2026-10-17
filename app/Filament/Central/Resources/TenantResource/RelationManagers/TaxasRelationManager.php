<?php

namespace App\Filament\Central\Resources\TenantResource\RelationManagers;

use App\Models\TaxaConfiguracao;
use App\Models\TenantTaxa;
use App\Services\ComplementoException;
use App\Services\TaxasService;
use App\Support\Precos;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Taxas de configuração do cliente (pagamento único, opcionais): o que aprovou, quanto, e a situação da cobrança. */
class TaxasRelationManager extends RelationManager
{
    protected static string $relationship = 'taxas';

    protected static ?string $title = 'Taxas de configuração';

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
            ->defaultSort('aprovada_em', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Taxa')->weight('bold'),
                Tables\Columns\TextColumn::make('valor')->label('Valor aprovado')->money('BRL')->description('pagamento único'),
                Tables\Columns\TextColumn::make('origem')->label('Origem')->formatStateUsing(fn ($state) => $state === 'central' ? 'Central' : 'Checkout do site'),
                Tables\Columns\TextColumn::make('aprovada_em')->label('Aprovada em')->dateTime('d/m/Y H:i')->description(fn (TenantTaxa $r) => $r->aprovada_ip ? 'IP '.$r->aprovada_ip : null),
                Tables\Columns\TextColumn::make('status')->label('Situação')->badge()
                    ->formatStateUsing(fn ($state) => TenantTaxa::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        TenantTaxa::PAGA => 'success', TenantTaxa::COBRADA => 'warning', TenantTaxa::CANCELADA => 'gray', default => 'info'
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('contratar')->label('Contratar taxa')->icon('heroicon-o-plus-circle')
                    ->form([
                        Forms\Components\Select::make('codigo')->label('Taxa')->native(false)->required()->live()
                            ->options(fn () => TaxaConfiguracao::disponiveis()->get()
                                ->reject(fn ($t) => $this->getOwnerRecord()->taxas()->withoutGlobalScopes()->where('taxa_codigo', $t->codigo)->where('status', '!=', TenantTaxa::CANCELADA)->exists())
                                ->mapWithKeys(fn ($t) => [$t->codigo => $t->nome.' — '.Precos::brl((float) $t->valor)])->all()),
                        Forms\Components\TextInput::make('valor')->label('Valor diferente do catálogo (opcional)')->numeric()->minValue(0.01)->step(0.01)
                            ->helperText('Deixe vazio para usar o valor do catálogo.'),
                        Forms\Components\Textarea::make('motivo')->label('Motivo do valor diferente')->rows(2)
                            ->required(fn (Forms\Get $get) => filled($get('valor'))),
                    ])
                    ->action(fn (array $data) => $this->executar(function () use ($data) {
                        app(TaxasService::class)->aprovar($this->getOwnerRecord(), [$data['codigo']], 'central', null, auth()->user(),
                            filled($data['valor'] ?? null) ? (float) $data['valor'] : null, $data['motivo'] ?? null);
                    }, 'Taxa aprovada', 'Use "Gerar cobrança" para enviar o pagamento ao cliente.')),
                Tables\Actions\Action::make('cobrarTodas')->label('Gerar cobrança das taxas')->icon('heroicon-o-banknotes')->color('success')
                    ->visible(fn () => $this->getOwnerRecord()->taxas()->withoutGlobalScopes()->where('status', TenantTaxa::APROVADA)->exists())
                    ->requiresConfirmation()->modalDescription('Gera uma única cobrança, no Asaas da Oravel, com todas as taxas aprovadas e ainda não cobradas.')
                    ->action(fn () => $this->executar(function () {
                        if (! app(TaxasService::class)->cobrar($this->getOwnerRecord()->refresh())) {
                            throw new ComplementoException('A cobrança não foi gerada (o cliente precisa estar cadastrado no Asaas).');
                        }
                    }, 'Cobrança gerada')),
            ])
            ->actions([
                Tables\Actions\Action::make('link')->label('Link de pagamento')->icon('heroicon-o-link')->color('gray')
                    ->visible(fn (TenantTaxa $r) => filled($r->invoice_url) && $r->status === TenantTaxa::COBRADA)
                    ->url(fn (TenantTaxa $r) => $r->invoice_url)->openUrlInNewTab(),
                Tables\Actions\Action::make('cancelar')->label('Cancelar')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (TenantTaxa $r) => in_array($r->status, [TenantTaxa::APROVADA, TenantTaxa::COBRADA], true))
                    ->requiresConfirmation()->modalDescription('Só vale para taxa ainda não paga. Se já foi cobrada, a cobrança é cancelada no Asaas e as outras taxas dela voltam para "aprovada".')
                    ->action(fn (TenantTaxa $r) => $this->executar(fn () => app(TaxasService::class)->cancelar($r), 'Taxa cancelada')),
            ]);
    }
}
