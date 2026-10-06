<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AcoesOleo;
use App\Filament\Resources\FrotaTrocaOleoResource\Pages;
use App\Models\Asset;
use App\Models\FrotaTrocaOleo;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Histórico de trocas e reposições de óleo da frota (o registro é por esta tela ou pelo celular). */
class FrotaTrocaOleoResource extends BaseResource
{
    use AcoesOleo;

    protected static ?string $model = FrotaTrocaOleo::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Óleo (trocas e reposições)';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Registro de óleo';

    protected static ?string $pluralModelLabel = 'Trocas e reposições de óleo';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['veiculo', 'coleta']))
            ->defaultSort('realizado_em', 'desc')
            ->headerActions([self::acaoRegistrarOleo()->label('Registrar óleo')])
            ->columns([
                Tables\Columns\TextColumn::make('realizado_em')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Placa')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('veiculo.name')->label('Veículo')->toggleable(),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn (string $state) => FrotaTrocaOleo::tipoLabels()[$state] ?? $state)
                    ->color(fn (string $state) => $state === FrotaTrocaOleo::TROCA ? 'success' : 'info'),
                Tables\Columns\TextColumn::make('litros')->label('Litros')->suffix(' L'),
                Tables\Columns\TextColumn::make('produto')->label('Produto')->searchable(),
                Tables\Columns\TextColumn::make('odometro')->label('Odômetro')->numeric(thousandsSeparator: '.')->suffix(' km'),
                Tables\Columns\TextColumn::make('proxima')->label('Próxima troca')->placeholder('—')
                    ->state(fn (FrotaTrocaOleo $r) => collect([$r->proxima_troca_odometro ? number_format($r->proxima_troca_odometro, 0, ',', '.').' km' : null, $r->proxima_troca_data?->format('d/m/Y')])->filter()->implode(' ou ') ?: null),
                Tables\Columns\TextColumn::make('destinacao')->label('Óleo usado')->badge()
                    ->state(fn (FrotaTrocaOleo $r) => $r->tipo === FrotaTrocaOleo::TROCA ? ($r->coleta_oleo_usado_id ? 'Coletado' : 'Sem destinação') : null)
                    ->color(fn (?string $state) => $state === 'Coletado' ? 'success' : 'danger')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ativo_id')->label('Veículo')->options(fn () => Asset::opcoesVeiculos())->searchable(),
                Tables\Filters\SelectFilter::make('tipo')->label('Tipo')->options(FrotaTrocaOleo::tipoLabels()),
                Tables\Filters\Filter::make('sem_destinacao')->label('Óleo usado sem destinação')->toggle()
                    ->query(fn (Builder $query) => $query->pendenteDeDestinacao()),
            ])
            ->emptyStateHeading('Nenhum registro de óleo')
            ->emptyStateDescription('Registre a troca ou a reposição aqui, ou pelo celular, pelo QR Code do veículo.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFrotaTrocasOleo::route('/')];
    }
}
