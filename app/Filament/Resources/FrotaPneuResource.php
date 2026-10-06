<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AcoesPneu;
use App\Filament\Resources\FrotaPneuResource\Pages;
use App\Filament\Resources\FrotaPneuResource\RelationManagers;
use App\Models\Asset;
use App\Models\FrotaPneu;
use App\Services\Frota\PneuService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FrotaPneuResource extends BaseResource
{
    use AcoesPneu;

    protected static ?string $model = FrotaPneu::class;

    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Pneus';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Pneu';

    protected static ?string $pluralModelLabel = 'Pneus';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')->columns(3)->schema([
                Forms\Components\TextInput::make('numero_fogo')->label('Nº de fogo')->required()->maxLength(60)
                    ->rule(fn (?FrotaPneu $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                        $existe = FrotaPneu::query()->whereRaw('lower(numero_fogo) = ?', [mb_strtolower(trim((string) $value))])
                            ->when($record, fn ($q) => $q->where('id', '!=', $record->id))->exists();
                        if ($existe) {
                            $fail('Já existe um pneu com este número de fogo.');
                        }
                    }),
                Forms\Components\TextInput::make('marca')->label('Marca')->maxLength(100),
                Forms\Components\TextInput::make('modelo')->label('Modelo')->maxLength(100),
                Forms\Components\TextInput::make('medida')->label('Medida')->placeholder('295/80 R22.5')->maxLength(40),
                Forms\Components\TextInput::make('dot')->label('DOT (semana e ano)')->placeholder('3524')->maxLength(4)
                    ->helperText('4 dígitos: semana (01 a 53) e ano. Ex.: 3524 = semana 35 de 2024.')
                    ->regex('/^(0[1-9]|[1-4]\d|5[0-3])\d{2}$/')->validationMessages(['regex' => 'Use semana (01 a 53) e ano, com 4 dígitos. Ex.: 3524.']),
                Forms\Components\Select::make('vida')->label('Vida')->options(FrotaPneu::vidaLabels())->default(FrotaPneu::VIDA_NOVO)->required()->native(false),
            ]),
            Forms\Components\Section::make('Almoxarifado (opcional)')->columns(2)->schema([
                Forms\Components\Select::make('peca_id')->label('Item no estoque')->searchable()->native(false)->live()
                    ->options(fn () => \App\Services\Frota\EstoqueFrotaService::opcoesPecas())
                    ->helperText('Escolha a peça cadastrada em Peças e Insumos. Ao salvar, entra 1 unidade no almoxarifado; ao montar, sai; ao devolver, volta.'),
                Forms\Components\Select::make('almoxarifado_id')->label('Almoxarifado')->searchable()->native(false)
                    ->options(fn () => \App\Services\Frota\EstoqueFrotaService::opcoesAlmoxarifados())
                    ->required(fn (Forms\Get $get) => filled($get('peca_id')))
                    ->disabledOn('edit'),
            ]),
            Forms\Components\Section::make('Medidas e custo')->columns(4)->schema([
                Forms\Components\TextInput::make('sulco_inicial_mm')->label('Sulco inicial (mm)')->numeric()->minValue(0)->maxValue(40),
                Forms\Components\TextInput::make('custo')->label('Custo (R$)')->numeric()->minValue(0)->prefix('R$'),
                Forms\Components\TextInput::make('pressao_min_psi')->label('Pressão mínima (psi)')->numeric()->minValue(0),
                Forms\Components\TextInput::make('pressao_max_psi')->label('Pressão máxima (psi)')->numeric()->minValue(0)->gte('pressao_min_psi'),
            ]),
            Forms\Components\Placeholder::make('situacao_info')->label('Situação')->visibleOn('edit')
                ->content(fn (?FrotaPneu $record) => FrotaPneu::situacaoLabels()[$record?->situacao] ?? '—')
                ->helperText('A situação muda pelas ações da lista: Montar, Remover, Recapagem.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['instalacoes.ativo', 'inspecoes']))
            ->defaultSort('numero_fogo')
            ->columns([
                Tables\Columns\TextColumn::make('numero_fogo')->label('Nº de fogo')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('marca')->label('Pneu')->searchable()
                    ->formatStateUsing(fn (FrotaPneu $record) => trim(($record->marca ?? '').' '.($record->modelo ?? '')) ?: '—')
                    ->description(fn (FrotaPneu $record) => $record->medida),
                Tables\Columns\TextColumn::make('vida')->label('Vida')->badge()->formatStateUsing(fn (string $state) => FrotaPneu::vidaLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('situacao')->label('Situação')->badge()
                    ->formatStateUsing(fn (string $state) => FrotaPneu::situacaoLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        FrotaPneu::MONTADO => 'success', FrotaPneu::EM_RECAPAGEM => 'warning', FrotaPneu::SUCATEADO => 'gray', default => 'info',
                    }),
                Tables\Columns\TextColumn::make('veiculo')->label('Veículo / posição')
                    ->state(function (FrotaPneu $record) {
                        $aberta = $record->instalacoes->first(fn ($i) => $i->removido_em === null);

                        return $aberta ? ($aberta->ativo?->placa ?: $aberta->ativo?->name).' · '.$aberta->posicao : null;
                    })->placeholder('—'),
                Tables\Columns\TextColumn::make('km')->label('Km rodado')->state(fn (FrotaPneu $record) => $record->kmRodado())->numeric(thousandsSeparator: '.')->suffix(' km'),
                Tables\Columns\TextColumn::make('custo_km')->label('Custo por km')->state(fn (FrotaPneu $record) => $record->custoPorKm())->money('BRL')->placeholder('—'),
                Tables\Columns\TextColumn::make('sulco')->label('Sulco')->state(fn (FrotaPneu $record) => $record->ultimaInspecao()?->sulco_mm)->suffix(' mm')->placeholder('—'),
                Tables\Columns\TextColumn::make('alertas')->label('Alertas')->badge()
                    ->state(fn (FrotaPneu $record) => collect($record->alertas())->pluck('mensagem')->all())
                    ->color(fn (FrotaPneu $record) => collect($record->alertas())->contains('gravidade', 'critica') ? 'danger' : 'warning')
                    ->placeholder('—')->wrap(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('situacao')->label('Situação')->options(FrotaPneu::situacaoLabels()),
                Tables\Filters\SelectFilter::make('vida')->label('Vida')->options(FrotaPneu::vidaLabels()),
            ])
            ->actions([
                Tables\Actions\Action::make('montar')->label('Montar')->icon('heroicon-o-arrow-up-tray')->color('success')
                    ->visible(fn (FrotaPneu $record) => $record->situacao === FrotaPneu::ESTOQUE)
                    ->form([
                        Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->live()->native(false)
                            ->options(fn () => Asset::query()->where('grupo', Asset::GRUPO_VEICULO)->orderBy('placa')->get()->mapWithKeys(fn (Asset $a) => [$a->id => trim(($a->placa ? $a->placa.' — ' : '').$a->name)])->all())
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('odometro', self::odometroAtual($state))),
                        Forms\Components\Select::make('posicao')->label('Posição (somente as livres)')->required()->native(false)
                            ->options(fn (Forms\Get $get) => self::posicoesLivres($get('ativo_id'))),
                        Forms\Components\TextInput::make('odometro')->label('Odômetro do veículo (km)')->numeric()->required(),
                    ])
                    ->action(fn (FrotaPneu $record, array $data) => self::executar(
                        fn () => app(PneuService::class)->montar($record, Asset::findOrFail($data['ativo_id']), $data['posicao'], (int) $data['odometro'], auth()->user()),
                        'Pneu montado'
                    )),
                self::acaoRodizio()->visible(fn (FrotaPneu $record) => $record->situacao === FrotaPneu::MONTADO),
                self::acaoRemover()->visible(fn (FrotaPneu $record) => $record->situacao === FrotaPneu::MONTADO),
                Tables\Actions\Action::make('concluir_recapagem')->label('Voltou da recapagem')->icon('heroicon-o-check-badge')->color('warning')
                    ->visible(fn (FrotaPneu $record) => $record->situacao === FrotaPneu::EM_RECAPAGEM)
                    ->form([
                        Forms\Components\TextInput::make('custo')->label('Custo da recapagem (R$)')->numeric()->minValue(0)->prefix('R$'),
                        Forms\Components\TextInput::make('sulco')->label('Sulco novo (mm)')->numeric()->minValue(0),
                    ])
                    ->action(fn (FrotaPneu $record, array $data) => self::executar(
                        fn () => app(PneuService::class)->concluirRecapagem($record, filled($data['custo'] ?? null) ? (float) $data['custo'] : null, filled($data['sulco'] ?? null) ? (float) $data['sulco'] : null),
                        'Recapagem concluída: pneu de volta ao estoque'
                    )),
                Tables\Actions\Action::make('inspecionar')->label('Sulco / pressão')->icon('heroicon-o-magnifying-glass')
                    ->visible(fn (FrotaPneu $record) => in_array($record->situacao, [FrotaPneu::MONTADO, FrotaPneu::ESTOQUE], true))
                    ->form([
                        Forms\Components\TextInput::make('sulco')->label('Sulco (mm)')->numeric()->minValue(0),
                        Forms\Components\TextInput::make('pressao')->label('Pressão (psi)')->numeric()->minValue(0),
                        Forms\Components\Textarea::make('observacao')->label('Observação')->rows(2),
                    ])
                    ->action(fn (FrotaPneu $record, array $data) => self::executar(
                        fn () => app(PneuService::class)->registrarInspecao($record, filled($data['sulco'] ?? null) ? (float) $data['sulco'] : null, filled($data['pressao'] ?? null) ? (float) $data['pressao'] : null, null, $data['observacao'] ?? null),
                        'Medição registrada'
                    )),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (FrotaPneu $record) => $record->instalacoes->isEmpty() && $record->inspecoes->isEmpty())
                    ->modalDescription('Só é possível excluir um pneu que nunca foi montado nem medido. Para os demais, marque como sucata.'),
            ])
            ->emptyStateHeading('Nenhum pneu cadastrado')
            ->emptyStateDescription('Cadastre os pneus (nº de fogo, marca, medida, DOT) e depois monte cada um na posição do veículo.');
    }

    public static function getRelations(): array
    {
        return [RelationManagers\InstalacoesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFrotaPneus::route('/'),
            'create' => Pages\CreateFrotaPneu::route('/create'),
            'edit' => Pages\EditFrotaPneu::route('/{record}/edit'),
        ];
    }
}
