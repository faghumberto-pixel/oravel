<?php

namespace App\Filament\Resources\AssetResource\RelationManagers;

use App\Filament\Concerns\AcoesOleo;
use App\Models\Asset;
use App\Models\FrotaTrocaOleo;
use App\Services\Frota\OleoStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Aba "Óleo" da ficha do veículo: situação da próxima troca, histórico e o botão de registrar. */
class OleoRelationManager extends RelationManager
{
    use AcoesOleo;

    protected static string $relationship = 'trocasOleo';

    protected static ?string $title = 'Óleo';

    public static function canViewForRecord(Model $ownerRecord, string $pageName): bool
    {
        return $ownerRecord instanceof Asset && $ownerRecord->isVehicle();
    }

    public function table(Table $table): Table
    {
        $status = OleoStatus::para($this->getOwnerRecord());
        $consumo = OleoStatus::consumoAnormal($this->getOwnerRecord());

        return $table
            ->heading($status['mensagem'].($consumo ? ' — ⚠ consumo anormal: '.$consumo['litros'].' L repostos em '.$consumo['km'].' km' : ''))
            ->columns([
                Tables\Columns\TextColumn::make('realizado_em')->label('Data')->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn (string $state) => FrotaTrocaOleo::tipoLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('litros')->label('Litros')->suffix(' L'),
                Tables\Columns\TextColumn::make('produto')->label('Produto'),
                Tables\Columns\TextColumn::make('odometro')->label('Odômetro')->numeric(thousandsSeparator: '.')->suffix(' km'),
                Tables\Columns\TextColumn::make('proxima_troca_odometro')->label('Próxima (km)')->numeric(thousandsSeparator: '.')->placeholder('—'),
                Tables\Columns\TextColumn::make('proxima_troca_data')->label('Próxima (data)')->date('d/m/Y')->placeholder('—'),
            ])
            ->headerActions([self::acaoRegistrarOleo(fn () => $this->getOwnerRecord())])
            ->defaultSort('realizado_em', 'desc')
            ->paginated(false)
            ->emptyStateHeading('Nenhum registro de óleo');
    }
}
