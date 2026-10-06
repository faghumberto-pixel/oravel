<?php

namespace App\Filament\Resources\AssetResource\RelationManagers;

use App\Filament\Concerns\AcoesPneu;
use App\Models\Asset;
use App\Models\FrotaInstalacaoComponente;
use App\Models\FrotaPneu;
use App\Services\Frota\PneuPosicoes;
use App\Services\Frota\PneuService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Aba "Pneus" da ficha do veículo: o que está montado em cada posição, com ações de montar, remover e rodízio. */
class PneusRelationManager extends RelationManager
{
    use AcoesPneu;

    protected static string $relationship = 'pneusMontados';

    protected static ?string $title = 'Pneus';

    public static function canViewForRecord(Model $ownerRecord, string $pageName): bool
    {
        return $ownerRecord instanceof Asset && $ownerRecord->isVehicle();
    }

    public function table(Table $table): Table
    {
        /** @var Asset $ativo */
        $ativo = $this->getOwnerRecord();
        $livres = self::posicoesLivres($ativo->id);

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('componente.inspecoes'))
            ->description($livres ? 'Posições livres: '.implode(', ', array_keys($livres)) : 'Todas as posições estão ocupadas.')
            ->columns([
                Tables\Columns\TextColumn::make('posicao')->label('Posição')->weight('bold')
                    ->formatStateUsing(fn (string $state) => $state)
                    ->description(fn (FrotaInstalacaoComponente $r) => PneuPosicoes::para($this->getOwnerRecord())[$r->posicao] ?? null),
                Tables\Columns\TextColumn::make('componente.numero_fogo')->label('Nº de fogo')->searchable(),
                Tables\Columns\TextColumn::make('pneu')->label('Pneu')
                    ->state(fn (FrotaInstalacaoComponente $r) => trim(($r->componente?->marca ?? '').' '.($r->componente?->modelo ?? '')).' '.($r->componente?->medida ?? '')),
                Tables\Columns\TextColumn::make('vida')->label('Vida')->badge()->state(fn (FrotaInstalacaoComponente $r) => FrotaPneu::vidaLabels()[$r->componente?->vida] ?? '—'),
                Tables\Columns\TextColumn::make('sulco')->label('Sulco')->suffix(' mm')->placeholder('—')->state(fn (FrotaInstalacaoComponente $r) => $r->componente?->ultimaInspecao()?->sulco_mm),
                Tables\Columns\TextColumn::make('km_no_veiculo')->label('Km nesta montagem')->suffix(' km')
                    ->state(fn (FrotaInstalacaoComponente $r) => max(0, (int) floor((float) $this->getOwnerRecord()->odometro_atual) - (int) $r->odometro_instalacao)),
                Tables\Columns\TextColumn::make('alertas')->label('Alertas')->badge()->placeholder('—')->wrap()
                    ->state(fn (FrotaInstalacaoComponente $r) => collect($r->componente?->alertas() ?? [])->pluck('mensagem')->all())
                    ->color(fn (FrotaInstalacaoComponente $r) => collect($r->componente?->alertas() ?? [])->contains('gravidade', 'critica') ? 'danger' : 'warning'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('montar')->label('Montar pneu')->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\Select::make('pneu_id')->label('Pneu (em estoque)')->required()->searchable()->native(false)
                            ->options(fn () => FrotaPneu::query()->where('situacao', FrotaPneu::ESTOQUE)->orderBy('numero_fogo')->get()->mapWithKeys(fn (FrotaPneu $p) => [$p->id => trim($p->numero_fogo.' — '.($p->marca ?? '').' '.($p->medida ?? ''), ' —')])->all()),
                        Forms\Components\Select::make('posicao')->label('Posição (somente as livres)')->required()->native(false)
                            ->options(fn () => self::posicoesLivres($this->getOwnerRecord()->id)),
                        Forms\Components\TextInput::make('odometro')->label('Odômetro do veículo (km)')->numeric()->required()
                            ->default(fn () => self::odometroAtual($this->getOwnerRecord()->id)),
                    ])
                    ->action(fn (array $data) => self::executar(
                        fn () => app(PneuService::class)->montar(FrotaPneu::findOrFail($data['pneu_id']), $this->getOwnerRecord(), $data['posicao'], (int) $data['odometro'], auth()->user()),
                        'Pneu montado'
                    )),
            ])
            ->actions([
                self::acaoRodizio(fn (FrotaInstalacaoComponente $linha) => $linha->componente),
                self::acaoRemover(fn (FrotaInstalacaoComponente $linha) => $linha->componente),
            ])
            ->paginated(false)
            ->emptyStateHeading('Nenhum pneu montado')
            ->emptyStateDescription('Use "Montar pneu" para colocar um pneu do estoque em uma posição.');
    }
}
