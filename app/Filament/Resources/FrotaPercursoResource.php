<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaPercursoResource\Pages;
use App\Models\Client;
use App\Models\FrotaPercurso;
use App\Services\Frota\KmRodadoService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

/**
 * Percursos da frota: trajetos entre dois locais (empresa ↔ cliente, cliente ↔ cliente...) que recebem o km real das viagens
 * (entradas e saídas de veículos), nos dois sentidos. Mostra viagens, km total, km médio e desvio contra o km previsto.
 */
class FrotaPercursoResource extends BaseResource
{
    protected static ?string $model = FrotaPercurso::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationParentItem = 'Frota';

    protected static ?string $navigationLabel = 'Percursos';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Percurso';

    protected static ?string $pluralModelLabel = 'Percursos';

    /** Totais por percurso calculados uma vez por requisição (a tabela pede vários por linha). */
    private static ?Collection $totais = null;

    private static function totais(): Collection
    {
        return self::$totais ??= app(KmRodadoService::class)->porPercurso()->keyBy(fn ($l) => $l['percurso']->id);
    }

    /** @return array<int, Forms\Components\Component> */
    private static function campoLocal(string $prefixo, string $titulo): array
    {
        return [
            Forms\Components\Select::make("local_{$prefixo}_tipo")->label($titulo)->options(FrotaPercurso::TIPOS)->required()->native(false)->live(),
            Forms\Components\Select::make("local_{$prefixo}_cliente_id")->label('Cliente')->searchable()->native(false)
                ->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id')->all())
                ->visible(fn (Forms\Get $get) => $get("local_{$prefixo}_tipo") === 'cliente')->required(fn (Forms\Get $get) => $get("local_{$prefixo}_tipo") === 'cliente'),
            Forms\Components\TextInput::make("local_{$prefixo}_texto")->label('Nome do local')->maxLength(191)
                ->visible(fn (Forms\Get $get) => $get("local_{$prefixo}_tipo") === 'outro')->required(fn (Forms\Get $get) => $get("local_{$prefixo}_tipo") === 'outro'),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Percurso')->schema([
                Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(191)->placeholder('Ex.: Empresa ↔ Topmixx')->columnSpanFull(),
                Forms\Components\Section::make('Local A')->schema(self::campoLocal('a', 'Tipo'))->columns(1)->columnSpan(1),
                Forms\Components\Section::make('Local B')->schema(self::campoLocal('b', 'Tipo'))->columns(1)->columnSpan(1),
                Forms\Components\TextInput::make('km_previsto')->label('Km previsto (um sentido)')->numeric()->minValue(0)->suffix('km')
                    ->helperText('Só para comparar com o km real das viagens. O km que vale é o do odômetro.'),
                Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true),
                Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['clienteA', 'clienteB']))
            ->defaultSort('nome')
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Percurso')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('trajeto')->label('Trajeto (nos dois sentidos)')->state(fn (FrotaPercurso $r) => $r->trajeto()),
                Tables\Columns\TextColumn::make('km_previsto')->label('Km previsto')->numeric(1, ',', '.')->placeholder('—')->suffix(' km'),
                Tables\Columns\TextColumn::make('viagens_total')->label('Viagens')->state(fn (FrotaPercurso $r) => self::totais()->get($r->id)['viagens'] ?? 0),
                Tables\Columns\TextColumn::make('km_total')->label('Km rodado')->state(fn (FrotaPercurso $r) => number_format(self::totais()->get($r->id)['km_total'] ?? 0, 0, ',', '.').' km')->weight('bold'),
                Tables\Columns\TextColumn::make('km_medio')->label('Km médio')->state(fn (FrotaPercurso $r) => ($m = self::totais()->get($r->id)['km_medio'] ?? null) !== null ? number_format($m, 1, ',', '.').' km' : '—'),
                Tables\Columns\TextColumn::make('desvio')->label('Desvio')->badge()
                    ->state(fn (FrotaPercurso $r) => ($d = self::totais()->get($r->id)['desvio_pct'] ?? null) === null ? '—' : ($d > 0 ? '+' : '').number_format($d, 1, ',', '.').'%')
                    ->color(fn (FrotaPercurso $r) => match (true) {
                        ($d = self::totais()->get($r->id)['desvio_pct'] ?? null) === null => 'gray',
                        abs($d) > 20 => 'danger',
                        abs($d) > 10 => 'warning',
                        default => 'success',
                    })
                    ->tooltip('Km médio real contra o km previsto'),
                Tables\Columns\IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->filters([Tables\Filters\TernaryFilter::make('ativo')->label('Ativo')])
            ->actions([
                Tables\Actions\EditAction::make(),
                // Percurso que já recebeu viagens não é apagado (perderia o histórico): desative.
                Tables\Actions\DeleteAction::make()->visible(fn (FrotaPercurso $r) => ! $r->viagens()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFrotaPercursos::route('/'),
            'create' => Pages\CreateFrotaPercurso::route('/create'),
            'edit' => Pages\EditFrotaPercurso::route('/{record}/edit'),
        ];
    }
}
