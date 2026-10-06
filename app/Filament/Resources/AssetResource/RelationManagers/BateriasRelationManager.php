<?php

namespace App\Filament\Resources\AssetResource\RelationManagers;

use App\Filament\Concerns\AcoesBateria;
use App\Models\Asset;
use App\Models\FrotaBateria;
use App\Models\FrotaInstalacaoComponente;
use App\Services\Frota\BateriaService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Aba "Baterias" da ficha do veículo: o que está instalado, idade, garantia, última tensão e as ações de instalar, remover e testar. */
class BateriasRelationManager extends RelationManager
{
    use AcoesBateria;

    protected static string $relationship = 'bateriasMontadas';

    protected static ?string $title = 'Baterias';

    public static function canViewForRecord(Model $ownerRecord, string $pageName): bool
    {
        return $ownerRecord instanceof Asset && $ownerRecord->isVehicle();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('componente.testes'))
            ->columns([
                Tables\Columns\TextColumn::make('bateria')->label('Bateria')->weight('bold')
                    ->state(fn (FrotaInstalacaoComponente $r) => $r->componente?->rotulo())
                    ->description(fn (FrotaInstalacaoComponente $r) => $r->componente?->numero_serie ? 'Série '.$r->componente->numero_serie : null),
                Tables\Columns\TextColumn::make('instalado_em')->label('Instalada em')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('idade')->label('Idade')->placeholder('—')->state(fn (FrotaInstalacaoComponente $r) => $r->componente?->idadeMeses() !== null ? $r->componente->idadeMeses().' meses' : null),
                Tables\Columns\TextColumn::make('garantia')->label('Garantia até')->date('d/m/Y')->placeholder('—')->state(fn (FrotaInstalacaoComponente $r) => $r->componente?->garantia_ate),
                Tables\Columns\TextColumn::make('tensao')->label('Última tensão')->suffix(' V')->placeholder('—')->state(fn (FrotaInstalacaoComponente $r) => $r->componente?->ultimoTeste()?->tensao),
                Tables\Columns\TextColumn::make('km')->label('Km nesta instalação')->suffix(' km')
                    ->state(fn (FrotaInstalacaoComponente $r) => max(0, (int) floor((float) $this->getOwnerRecord()->odometro_atual) - (int) $r->odometro_instalacao)),
                Tables\Columns\TextColumn::make('alertas')->label('Alertas')->badge()->placeholder('—')->wrap()
                    ->state(fn (FrotaInstalacaoComponente $r) => collect($r->componente?->alertas() ?? [])->pluck('mensagem')->all())
                    ->color(fn (FrotaInstalacaoComponente $r) => collect($r->componente?->alertas() ?? [])->contains('gravidade', 'critica') ? 'danger' : 'warning'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('instalar')->label('Instalar bateria')->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\Select::make('bateria_id')->label('Bateria (em estoque)')->required()->searchable()->native(false)
                            ->options(fn () => FrotaBateria::query()->where('situacao', FrotaBateria::ESTOQUE)->orderBy('created_at')->get()->mapWithKeys(fn (FrotaBateria $b) => [$b->id => $b->rotulo().($b->numero_serie ? ' — '.$b->numero_serie : '')])->all()),
                        Forms\Components\TextInput::make('odometro')->label('Odômetro do veículo (km)')->numeric()->required()
                            ->default(fn () => self::odometroDoVeiculo($this->getOwnerRecord()->id)),
                    ])
                    ->action(fn (array $data) => self::executarBateria(
                        fn () => app(BateriaService::class)->instalar(FrotaBateria::findOrFail($data['bateria_id']), $this->getOwnerRecord(), (int) $data['odometro'], auth()->user()),
                        'Bateria instalada'
                    )),
            ])
            ->actions([
                self::acaoTestarTensao(fn (FrotaInstalacaoComponente $linha) => $linha->componente),
                self::acaoRemoverBateria(fn (FrotaInstalacaoComponente $linha) => $linha->componente),
            ])
            ->paginated(false)
            ->emptyStateHeading('Nenhuma bateria instalada')
            ->emptyStateDescription('Use "Instalar bateria" para colocar uma bateria do estoque neste veículo.');
    }
}
