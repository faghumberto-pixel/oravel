<?php

namespace App\Filament\Resources\AssetResource\RelationManagers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoCumprimento;
use App\Models\CatalogoPreventiva;
use App\Models\CatalogoRequisito;
use App\Services\Catalogo\Aptidao\AptidaoGate;
use App\Services\Catalogo\Aptidao\AptidaoImpacto;
use App\Services\Catalogo\Aptidao\AssetFitnessService;
use App\Support\CatalogoAcesso;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** G1 fase 4 -- aptidao do ativo + registro de cumprimentos (documento, inspecao, checklist, preventiva). Historico imutavel: sem editar/excluir. */
class CumprimentosRelationManager extends RelationManager
{
    protected static string $relationship = 'cumprimentos';

    protected static ?string $title = 'Aptidão e cumprimentos';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return CatalogoAcesso::podeLer() && AptidaoGate::ligada($ownerRecord->tenant_id);
    }

    /** @return array<string, array<string, string>> */
    private function alvos(Asset $ativo): array
    {
        $grupo = $ativo->asset_category_id ? AssetCategory::withoutGlobalScopes()->where('tenant_id', $ativo->tenant_id)->find($ativo->asset_category_id) : null;

        if (! $grupo) {
            return [];
        }

        $reqs = CatalogoRequisito::withoutGlobalScopes()->with(['item' => fn ($q) => $q->withoutGlobalScopes()])->where('grupo_id', $grupo->id)
            ->where('ativo', true)->whereNull('deleted_at')->orderBy('ordem')->get()
            ->mapWithKeys(fn ($r) => ['req:'.$r->id => ($r->item?->nome ?? $r->slug).' ('.$r->efeito.')'])->all();
        $prevs = CatalogoPreventiva::withoutGlobalScopes()->where('grupo_id', $grupo->id)->where('ativo', true)->whereNull('deleted_at')->orderBy('ordem')->get()
            ->mapWithKeys(fn ($p) => ['prev:'.$p->id => $p->nome])->all();

        return array_filter(['Requisitos do grupo' => $reqs, 'Preventivas' => $prevs]);
    }

    private function alvoPermitido(Asset $ativo, string $alvo): bool
    {
        foreach ($this->alvos($ativo) as $opcoes) {
            if (array_key_exists($alvo, $opcoes)) {
                return true;
            }
        }

        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(function () {
                /** @var Asset $a */
                $a = $this->getOwnerRecord();
                $a->refresh();
                $status = AptidaoImpacto::rotulo((string) ($a->aptidao_status ?? 'não calculado'));
                $motivos = collect($a->aptidao_motivos['motivos'] ?? [])->pluck('texto')->filter()->implode(' · ');
                $avisos = collect($a->aptidao_motivos['avisos'] ?? [])->implode(' · ');

                return "Aptidão: {$status}".($motivos ? " — {$motivos}" : '').($avisos ? " | Avisos: {$avisos}" : '').($a->aptidao_bloqueia_alocacao ? ' | BLOQUEIA ALOCAÇÃO' : '');
            })
            ->defaultSort('realizado_em', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('realizado_em')->label('Realizado em')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('snapshot.nome')->label('Requisito / preventiva')->searchable(false),
                Tables\Columns\TextColumn::make('snapshot.efeito')->label('Efeito')->badge()->placeholder('Preventiva'),
                Tables\Columns\TextColumn::make('resultado')->label('Resultado')->badge()
                    ->color(fn (?string $state) => $state === 'ok' ? 'success' : 'danger')->formatStateUsing(fn (?string $state) => $state === 'ok' ? 'OK' : 'Reprovado'),
                Tables\Columns\TextColumn::make('valido_ate')->label('Válido até')->date('d/m/Y')->placeholder('—'),
                Tables\Columns\TextColumn::make('medidor_leitura')->label('Medidor')->placeholder('—'),
                Tables\Columns\TextColumn::make('observacao')->label('Observação')->limit(40)->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Registrar cumprimento')->modalHeading('Registrar cumprimento')
                    ->visible(fn () => CatalogoAcesso::podeGerenciar())
                    ->form(fn () => [
                        Forms\Components\Select::make('alvo')->label('O que foi cumprido')->required()->native(false)->searchable()->live()
                            ->options(fn () => $this->alvos($this->getOwnerRecord()))
                            ->helperText('Só aparecem os requisitos e preventivas do grupo deste ativo.')
                            // validacao no SERVIDOR: nunca confiar so na lista de opcoes (requisito de outro grupo/tenant nao pode ser gravado)
                            ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                                if (! $this->alvoPermitido($this->getOwnerRecord(), (string) $value)) {
                                    $fail('Este item não pertence ao grupo do ativo.');
                                }
                            }]),
                        Forms\Components\Select::make('resultado')->label('Resultado')->required()->default('ok')->native(false)
                            ->options(['ok' => 'OK / em dia', 'reprovado' => 'Reprovado / não conforme']),
                        Forms\Components\DateTimePicker::make('realizado_em')->label('Realizado em')->required()->default(now())->maxDate(now()->addMinute()),
                        Forms\Components\DatePicker::make('valido_ate')->label('Válido até')
                            ->helperText('Para laudo, inspeção ou documento sem validade padrão: informe a validade que consta no documento.')
                            ->visible(fn (Get $get) => str_starts_with((string) $get('alvo'), 'req:')),
                        Forms\Components\TextInput::make('medidor_leitura')->label('Leitura do medidor (horímetro / km)')->numeric()->minValue(0)
                            ->visible(fn (Get $get) => str_starts_with((string) $get('alvo'), 'prev:'))
                            ->default(fn () => max((float) $this->getOwnerRecord()->horimetro_atual, (float) $this->getOwnerRecord()->odometro_atual) ?: null)
                            ->helperText('Padrão: leitura atual do ativo. Ajuste para o medidor do plano (horímetro ou km).'),
                        Forms\Components\Textarea::make('observacao')->label('Observação')->rows(2),
                    ])
                    ->using(function (array $data, self $livewire): Model {
                        /** @var Asset $ativo */
                        $ativo = $livewire->getOwnerRecord();
                        abort_unless($livewire->alvoPermitido($ativo, (string) $data['alvo']), 403);
                        [$tipo, $id] = explode(':', (string) $data['alvo'], 2);

                        return CatalogoCumprimento::create([
                            'tenant_id' => $ativo->tenant_id, 'asset_id' => $ativo->id,
                            'requisito_id' => $tipo === 'req' ? $id : null, 'preventiva_id' => $tipo === 'prev' ? $id : null,
                            'resultado' => $tipo === 'prev' ? 'ok' : $data['resultado'], 'realizado_em' => $data['realizado_em'],
                            'valido_ate' => $data['valido_ate'] ?? null, 'medidor_leitura' => $data['medidor_leitura'] ?? null,
                            'observacao' => $data['observacao'] ?? null, 'origem' => 'manual', 'registrado_por' => auth()->id(),
                        ]);
                    }),
                Tables\Actions\Action::make('recalcular')->label('Recalcular aptidão agora')->icon('heroicon-o-arrow-path')->color('gray')
                    ->action(function (self $livewire) {
                        $r = app(AssetFitnessService::class)->recalcular($livewire->getOwnerRecord());
                        Notification::make()->success()->title('Aptidão: '.AptidaoImpacto::rotulo($r->status))->send();
                    }),
            ]);
    }
}
