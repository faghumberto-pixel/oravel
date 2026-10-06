<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VehicleExpirationResource\Pages;
use App\Models\Asset;
use App\Support\VehicleExpirations;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * "Vencimento de veículos": todos os veículos com o vencimento do seguro, IPVA, licenciamento e tacógrafo
 * (pesados), em cores, com abas por situação e filtro por documento. Usa o modelo Asset (grupo = veículo) e
 * a mesma permissão de Ativos.
 */
class VehicleExpirationResource extends BaseResource
{
    protected static ?string $model = Asset::class;

    protected static ?string $slug = 'vencimentos-veiculos';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Ativos';

    protected static ?string $navigationParentItem = 'Gestão de Ativos';

    protected static ?string $navigationLabel = 'Vencimento de veículos';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Veículo';

    protected static ?string $pluralModelLabel = 'Vencimento de veículos';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('assets.grupo', Asset::GRUPO_VEICULO)
            ->addSelect('assets.*')
            ->addSelect(DB::raw('LEAST(assets.seguro_vencimento, assets.ipva_vencimento, assets.licenciamento_vencimento, CASE WHEN assets.veiculo_pesado THEN assets.tacografo_vencimento END) as proximo_vencimento'));
    }

    private static function expirationColumn(string $column, string $label): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make($column)
            ->label($label)
            ->badge()
            ->state(fn (Asset $record) => $column === 'tacografo_vencimento' && ! $record->veiculo_pesado
                ? 'n/a'
                : (VehicleExpirations::status($record->{$column})[0]))
            ->color(fn (Asset $record) => $column === 'tacografo_vencimento' && ! $record->veiculo_pesado
                ? 'gray'
                : VehicleExpirations::status($record->{$column})[1])
            ->description(fn (Asset $record) => $column === 'tacografo_vencimento' && ! $record->veiculo_pesado
                ? 'só veículo pesado'
                : VehicleExpirations::status($record->{$column})[2])
            ->sortable();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('placa')->label('Placa')->searchable()->weight('bold')->placeholder('—'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Veículo')
                    ->searchable()
                    ->description(fn (Asset $record) => collect([$record->fabricante, $record->modelo])->filter()->implode(' ')),
                self::expirationColumn('seguro_vencimento', 'Seguro'),
                self::expirationColumn('ipva_vencimento', 'IPVA'),
                self::expirationColumn('licenciamento_vencimento', 'Licenciamento'),
                self::expirationColumn('tacografo_vencimento', 'Tacógrafo'),
            ])
            ->defaultSort('proximo_vencimento')
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByRaw('proximo_vencimento asc nulls last'))
            ->filters([
                Tables\Filters\SelectFilter::make('documento')
                    ->label('Documento')
                    ->options(VehicleExpirations::documents())
                    ->placeholder('Todos os documentos')
                    ->query(fn (Builder $query, array $data) => VehicleExpirations::apply($query, $data['value'] ?? null, null)),
                Tables\Filters\TernaryFilter::make('veiculo_pesado')->label('Veículo pesado'),
            ])
            ->actions([
                Tables\Actions\Action::make('abrir')
                    ->label('Abrir ativo')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Asset $record) => AssetResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('Nenhum veículo cadastrado')
            ->emptyStateDescription('Cadastre um ativo e escolha "Veículo" em "Tipo de ativo".');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListVehicleExpirations::route('/')];
    }
}
