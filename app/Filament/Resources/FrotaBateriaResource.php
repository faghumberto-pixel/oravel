<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AcoesBateria;
use App\Filament\Resources\FrotaBateriaResource\Pages;
use App\Filament\Resources\FrotaBateriaResource\RelationManagers;
use App\Models\Asset;
use App\Models\FrotaBateria;
use App\Services\Frota\BateriaService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FrotaBateriaResource extends BaseResource
{
    use AcoesBateria;

    protected static ?string $model = FrotaBateria::class;

    protected static ?string $navigationIcon = 'heroicon-o-battery-100';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Baterias';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Bateria';

    protected static ?string $pluralModelLabel = 'Baterias';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Bateria')->columns(3)->schema([
                Forms\Components\TextInput::make('marca')->label('Marca')->maxLength(100),
                Forms\Components\TextInput::make('modelo')->label('Modelo')->maxLength(100),
                Forms\Components\TextInput::make('numero_serie')->label('Nº de série')->maxLength(100),
                Forms\Components\TextInput::make('amperagem_ah')->label('Capacidade (Ah)')->numeric()->minValue(1)->maxValue(1000),
                Forms\Components\TextInput::make('cca')->label('CCA (partida a frio)')->numeric()->minValue(1)->maxValue(5000),
                Forms\Components\TextInput::make('custo')->label('Custo (R$)')->numeric()->minValue(0)->prefix('R$'),
                Forms\Components\DatePicker::make('comprada_em')->label('Data da compra')->maxDate(now()),
                Forms\Components\DatePicker::make('garantia_ate')->label('Garantia até'),
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
            Forms\Components\Placeholder::make('situacao_info')->label('Situação')->visibleOn('edit')
                ->content(fn (?FrotaBateria $record) => FrotaBateria::situacaoLabels()[$record?->situacao] ?? '—')
                ->helperText('A situação muda pelas ações da lista: Instalar e Remover.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['instalacoes.ativo', 'testes']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('marca')->label('Bateria')->searchable()->weight('bold')
                    ->formatStateUsing(fn (FrotaBateria $record) => $record->rotulo())
                    ->description(fn (FrotaBateria $record) => $record->numero_serie ? 'Série '.$record->numero_serie : null),
                Tables\Columns\TextColumn::make('situacao')->label('Situação')->badge()
                    ->formatStateUsing(fn (string $state) => FrotaBateria::situacaoLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        FrotaBateria::MONTADA => 'success', FrotaBateria::SUCATEADA => 'gray', default => 'info'
                    }),
                Tables\Columns\TextColumn::make('veiculo')->label('Veículo')->placeholder('—')
                    ->state(function (FrotaBateria $record) {
                        $aberta = $record->instalacoes->first(fn ($i) => $i->removido_em === null);

                        return $aberta ? ($aberta->ativo?->placa ?: $aberta->ativo?->name) : null;
                    }),
                Tables\Columns\TextColumn::make('idade')->label('Idade')->placeholder('—')->state(fn (FrotaBateria $record) => $record->idadeMeses() !== null ? $record->idadeMeses().' meses' : null),
                Tables\Columns\TextColumn::make('garantia_ate')->label('Garantia até')->date('d/m/Y')->placeholder('—'),
                Tables\Columns\TextColumn::make('tensao')->label('Última tensão')->placeholder('—')->suffix(' V')
                    ->state(fn (FrotaBateria $record) => $record->ultimoTeste()?->tensao),
                Tables\Columns\TextColumn::make('km')->label('Km rodados')->state(fn (FrotaBateria $record) => $record->kmRodado())->numeric(thousandsSeparator: '.')->suffix(' km')->toggleable(),
                Tables\Columns\TextColumn::make('alertas')->label('Alertas')->badge()->placeholder('—')->wrap()
                    ->state(fn (FrotaBateria $record) => collect($record->alertas())->pluck('mensagem')->all())
                    ->color(fn (FrotaBateria $record) => collect($record->alertas())->contains('gravidade', 'critica') ? 'danger' : 'warning'),
            ])
            ->filters([Tables\Filters\SelectFilter::make('situacao')->label('Situação')->options(FrotaBateria::situacaoLabels())])
            ->actions([
                Tables\Actions\Action::make('instalar')->label('Instalar')->icon('heroicon-o-arrow-up-tray')->color('success')
                    ->visible(fn (FrotaBateria $record) => $record->situacao === FrotaBateria::ESTOQUE)
                    ->form([
                        Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->live()->native(false)
                            ->options(fn () => Asset::query()->where('grupo', Asset::GRUPO_VEICULO)->orderBy('placa')->get()->mapWithKeys(fn (Asset $a) => [$a->id => trim(($a->placa ? $a->placa.' — ' : '').$a->name)])->all())
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('odometro', self::odometroDoVeiculo($state))),
                        Forms\Components\TextInput::make('odometro')->label('Odômetro do veículo (km)')->numeric()->required(),
                    ])
                    ->action(fn (FrotaBateria $record, array $data) => self::executarBateria(
                        fn () => app(BateriaService::class)->instalar($record, Asset::findOrFail($data['ativo_id']), (int) $data['odometro'], auth()->user()),
                        'Bateria instalada'
                    )),
                self::acaoRemoverBateria()->visible(fn (FrotaBateria $record) => $record->situacao === FrotaBateria::MONTADA),
                self::acaoTestarTensao()->visible(fn (FrotaBateria $record) => $record->situacao !== FrotaBateria::SUCATEADA),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (FrotaBateria $record) => $record->instalacoes->isEmpty() && $record->testes->isEmpty())
                    ->modalDescription('Só é possível excluir uma bateria que nunca foi instalada nem testada.'),
            ])
            ->emptyStateHeading('Nenhuma bateria cadastrada')
            ->emptyStateDescription('Cadastre as baterias (marca, capacidade, data da compra, garantia) e depois instale cada uma no veículo.');
    }

    public static function getRelations(): array
    {
        return [RelationManagers\InstalacoesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFrotaBaterias::route('/'),
            'create' => Pages\CreateFrotaBateria::route('/create'),
            'edit' => Pages\EditFrotaBateria::route('/{record}/edit'),
        ];
    }
}
